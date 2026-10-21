<?php

namespace App\Services;

use Exception;

class SorParserService
{
    /**
     * Speed of light in vacuum (m/s), per Telcordia SR-4731.
     */
    private const SPEED_OF_LIGHT = 299792458.0;

    /**
     * Propagation time unit: 10^-4 microseconds = 10^-10 seconds.
     */
    private const PROP_TIME_UNIT = 1.0e-10;

    /**
     * Sample spacing unit: 10^-8 microseconds = 10^-14 seconds.
     */
    private const SAMPLE_SPACING_UNIT = 1.0e-14;

    /**
     * Parse binary .sor file content or file path.
     *
     * @param  string  $input  File path or raw binary string
     * @param  bool  $isPath  True if $input is a file path
     * @return array Structured parsed OTDR trace data
     *
     * @throws Exception
     */
    public function parse(string $input, bool $isPath = true): array
    {
        $binary = $isPath ? @file_get_contents($input) : $input;
        if ($binary === false || strlen($binary) < 64) {
            throw new Exception('File kosong atau tidak dapat dibaca.');
        }

        $length = strlen($binary);

        // 1. Parse Map Block (Header)
        $offset = 0;
        $mapName = $this->readNullString($binary, $offset, $length);
        if ($mapName !== 'Map') {
            throw new Exception("Format file tidak valid. Magic header 'Map' tidak ditemukan.");
        }

        // The fixed portion of the map header must be fully readable.
        if ($offset + 8 > $length) {
            throw new Exception('Header Map terpotong: ukuran file tidak mencukupi.');
        }

        $mapHeader = unpack('vversion/Vsize/vnumBlocks', substr($binary, $offset, 8));
        if ($mapHeader === false) {
            throw new Exception('Header Map tidak dapat dibaca.');
        }
        $offset += 8;

        $mapSize = (int) $mapHeader['size'];
        $numBlocks = (int) $mapHeader['numBlocks'];

        // Map block must at least contain its own header and stay inside the file.
        if ($mapSize < $offset || $mapSize > $length) {
            throw new Exception("Ukuran blok Map tidak valid ({$mapSize} byte).");
        }

        $blocks = [];
        $currentOffset = $mapSize;

        // In SR-4731, the remaining (numBlocks - 1) block descriptors follow
        $blockCount = max(0, $numBlocks - 1);
        for ($i = 0; $i < $blockCount && $offset < $mapSize; $i++) {
            $bId = $this->readNullString($binary, $offset, $mapSize);
            if ($bId === '') {
                break;
            }

            // Descriptor header (version + size) must not cross the file end.
            if ($offset + 6 > $length) {
                throw new Exception("Descriptor blok '{$bId}' terpotong.");
            }

            $bHdr = unpack('vversion/Vsize', substr($binary, $offset, 6));
            if ($bHdr === false) {
                throw new Exception("Descriptor blok '{$bId}' tidak dapat dibaca.");
            }
            $offset += 6;

            $blockSize = (int) $bHdr['size'];

            // Reject negative (impossible on unsigned 32-bit) or out-of-file blocks.
            if ($blockSize < 0 || $currentOffset + $blockSize > $length) {
                throw new Exception(
                    "Blok '{$bId}' melewati batas file (offset {$currentOffset}, size {$blockSize}, file {$length})."
                );
            }

            $blocks[$bId] = [
                'name' => $bId,
                'version' => $bHdr['version'] / 100,
                'size' => $blockSize,
                'offset' => $currentOffset,
            ];
            $currentOffset += $blockSize;
        }

        // 2. Parse GenParams (General Parameters)
        $genParams = $this->parseGenParams($binary, $blocks['GenParams'] ?? null);

        // 3. Parse SupParams (Supplier/Device Parameters)
        $supParams = $this->parseSupParams($binary, $blocks['SupParams'] ?? null);

        // 4. Parse FxdParams (Fixed/Optical Parameters)
        $fxdParams = $this->parseFxdParams($binary, $blocks['FxdParams'] ?? null);

        // 5. Parse KeyEvents (Event Table)
        $keyEvents = $this->parseKeyEvents($binary, $blocks['KeyEvents'] ?? null, $fxdParams);

        // 6. Parse DataPts (Attenuation Trace Points)
        $dataPts = $this->parseDataPts($binary, $blocks['DataPts'] ?? null, $fxdParams);

        // 7. Calculate Summary Metrics
        // Prefer the farthest real event distance; otherwise fall back to the
        // full acquisition span (totalDataPts * sampleSpacing).
        $totalDistKm = 0.0;
        if (! empty($keyEvents)) {
            $lastEvent = end($keyEvents);
            if (! empty($lastEvent['distanceKm']) && $lastEvent['distanceKm'] > 0) {
                $totalDistKm = (float) $lastEvent['distanceKm'];
            }
        }
        if ($totalDistKm <= 0) {
            $totalDistKm = $fxdParams['spanKm'] ?? ($fxdParams['rangeKm'] ?? 0);
        }

        $summary = [
            'totalDistanceKm' => round($totalDistKm, 4),
            'wavelength' => $fxdParams['wavelength'] ?? 1310,
            'pulseWidth' => $fxdParams['pulseWidth'] ?? 0,
            'ior' => $fxdParams['groupIndex'] ?? 1.4677,
            'eventCount' => count($keyEvents),
            'supplier' => $supParams['supplier'] ?? 'N/A',
            'model' => $supParams['model'] ?? 'N/A',
            'serialNumber' => $supParams['serialNumber'] ?? 'N/A',
            'dateTime' => $fxdParams['dateTimeFormatted'] ?? date('Y-m-d H:i:s'),
            'cableId' => $genParams['cableId'] ?? '',
            'fiberId' => $genParams['fiberId'] ?? '',
        ];

        return [
            'success' => true,
            'summary' => $summary,
            'metadata' => [
                'general' => $genParams,
                'supplier' => $supParams,
                'fixed' => $fxdParams,
            ],
            'events' => $keyEvents,
            'dataPoints' => $dataPts,
            'map' => $blocks,
            'fileSize' => $length,
        ];
    }

    private function parseGenParams(string $b, ?array $block): array
    {
        if (! $block) {
            return [];
        }

        [$start, $end] = $this->resolveBlockBounds($b, $block);
        if ($start === null || $end === null || $end - $start < 1) {
            return [];
        }

        $off = $start;
        $this->readNullString($b, $off, $end); // Skip block name "GenParams"

        $strings = [];
        while ($off < $end) {
            $before = $off;
            $s = $this->readNullString($b, $off, $end);
            if ($off <= $before) {
                break; // defensive: readNullString must always advance
            }
            $strings[] = $s;
            if (count($strings) >= 15) {
                break;
            }
        }

        return [
            'languageCode' => $strings[0] ?? '',
            'cableId' => $strings[1] ?? '',
            'fiberId' => $strings[2] ?? '',
            'fiberType' => $strings[3] ?? '',
            'origin' => $strings[4] ?? '',
            'destination' => $strings[5] ?? '',
            'cableCode' => $strings[6] ?? '',
            'operator' => $strings[13] ?? ($strings[7] ?? ''),
            'comment' => $strings[14] ?? '',
            'allStrings' => array_values(array_filter($strings)),
        ];
    }

    private function parseSupParams(string $b, ?array $block): array
    {
        if (! $block) {
            return [];
        }

        [$start, $end] = $this->resolveBlockBounds($b, $block);
        if ($start === null || $end === null || $end - $start < 1) {
            return [];
        }

        $off = $start;
        $this->readNullString($b, $off, $end); // Skip "SupParams"

        $supplier = $this->readNullString($b, $off, $end);
        $model = $this->readNullString($b, $off, $end);
        $serial = $this->readNullString($b, $off, $end);
        $module = $this->readNullString($b, $off, $end);
        $moduleSn = $this->readNullString($b, $off, $end);
        $softVer = $this->readNullString($b, $off, $end);

        return [
            'supplier' => $supplier,
            'model' => $model,
            'serialNumber' => $serial,
            'module' => $module,
            'moduleSerialNumber' => $moduleSn,
            'softwareVersion' => $softVer,
        ];
    }

    private function parseFxdParams(string $b, ?array $block): array
    {
        if (! $block) {
            return [];
        }

        [$start, $end] = $this->resolveBlockBounds($b, $block);
        if ($start === null || $end === null || $end - $start < 1) {
            return [];
        }

        $off = $start;
        $this->readNullString($b, $off, $end); // Skip "FxdParams"

        // The fixed parameter record is 44 bytes and must fit inside the block.
        if ($off + 44 > $end) {
            return [];
        }

        $data = unpack(
            'VdateTime/'.
            'a2units/'.
            'vwavelength/'.
            'VacqOffset/'.
            'VacqOffsetDist/'.
            'vnumPulseWidths/'.
            'vpulseWidth/'.
            'VsampleSpacing/'.
            'VtotalDataPts/'.
            'VgroupIndex/'.
            'vbackscatter/'.
            'vnumAverages/'.
            'VaveragingTime/'.
            'Vrange',
            substr($b, $off, 44)
        );

        if (! $data) {
            return [];
        }

        // Group refractive index (index of refraction), stored as integer * 10^5.
        $groupIndex = ($data['groupIndex'] ?? 0) / 100000;
        if ($groupIndex <= 0) {
            $groupIndex = 1.4677; // sensible default for single-mode fiber
        }

        $totalDataPts = (int) ($data['totalDataPts'] ?? 0);
        $sampleSpacingRaw = (int) ($data['sampleSpacing'] ?? 0);

        // SR-4731: sample spacing is expressed in 10^-8 microseconds (1e-14 s).
        // spacing(m) = c * raw * 1e-14 / n
        $sampleSpacingMeters = (self::SPEED_OF_LIGHT * ($sampleSpacingRaw * self::SAMPLE_SPACING_UNIT)) / $groupIndex;

        // Instrument range field is expressed in 0.1 m units.
        $rangeMeters = ($data['range'] ?? 0) * 0.1;
        $rangeKm = $rangeMeters / 1000;

        // Fallback: if the derived spacing is physically implausible, derive it
        // from the configured range and the number of acquisition points.
        if ($sampleSpacingMeters < 0.001 || $sampleSpacingMeters > 50) {
            $sampleSpacingMeters = $totalDataPts > 0 ? ($rangeMeters / $totalDataPts) : 0.0;
        }

        $spanKm = ($sampleSpacingMeters * $totalDataPts) / 1000;

        return [
            'timestamp' => $data['dateTime'],
            'dateTimeFormatted' => date('Y-m-d H:i:s', $data['dateTime']),
            'units' => trim($data['units']),
            'wavelength' => round($data['wavelength'] / 10),
            'pulseWidth' => $data['pulseWidth'],
            'sampleSpacingRaw' => $sampleSpacingRaw,
            'sampleSpacingMeters' => round($sampleSpacingMeters, 6),
            'sampleSpacingMetersExact' => $sampleSpacingMeters,
            'totalDataPts' => $totalDataPts,
            'groupIndex' => round($groupIndex, 5),
            'backscatter' => round(-$data['backscatter'] / 10, 1),
            'numAverages' => $data['numAverages'],
            'rangeKm' => round($rangeKm, 4),
            'spanKm' => round($spanKm, 4),
        ];
    }

    private function parseKeyEvents(string $b, ?array $block, array $fxd): array
    {
        if (! $block) {
            return [];
        }

        $start = (int) $block['offset'];
        if ($start < 0 || $start >= strlen($b)) {
            return [];
        }

        $limit = min(strlen($b), $start + (int) $block['size']);
        if ($limit - $start < 22) {
            return [];
        }

        $data = substr($b, $start, $limit - $start);
        if (strlen($data) < 22) {
            return [];
        }

        $groupIndex = (float) ($fxd['groupIndex'] ?? 1.4677);
        if ($groupIndex <= 0) {
            $groupIndex = 1.4677;
        }

        // Block layout: "KeyEvents\0" + uint16 event count.
        $off = 0;
        $this->readNullString($data, $off); // Skip block name
        $countArr = unpack('vcount', substr($data, $off, 2));
        $numEvents = $countArr['count'] ?? 0;
        $off += 2;

        $events = [];
        $cumLoss = 0.0;
        $pos = $off;
        // Start of the record currently being decoded. Resync uses this to
        // reject candidates that overlap the record's fixed 22-byte span.
        $recordStart = $pos;
        $blockLen = strlen($data);
        $maxEvents = $numEvents > 0 ? $numEvents : 64;

        // Event code pattern: [0-9][A-Z][0-9]{4}[A-Z]{2}, header sits 14 bytes before it.
        $codePattern = '/^[0-9][A-Z][0-9]{4}[A-Z]{2}$/';

        while (count($events) < $maxEvents && $pos + 22 <= $blockLen) {
            $code = substr($data, $pos + 14, 8);

            // Offsets may drift when comments are variable length / null padded.
            // Re-sync by locating the next event code and backing up 14 bytes.
            // Guard: the candidate header must clear the fixed 22-byte span
            // (14-byte header + 8-byte code) of the record being replaced:
            // $candidatePos >= $recordStart + 22, hence $matchPos >= $recordStart + 36.
            if (! preg_match($codePattern, $code)) {
                $candidatePos = null;
                $minCandidate = $recordStart + 22;
                $searchFrom = $minCandidate;
                $iterations = 0;

                while ($searchFrom + 22 <= $blockLen && $iterations < 100) {
                    $iterations++;

                    if (! preg_match(
                        '/[0-9][A-Z][0-9]{4}[A-Z]{2}/',
                        substr($data, $searchFrom, $blockLen - $searchFrom),
                        $m,
                        PREG_OFFSET_CAPTURE
                    )) {
                        break;
                    }

                    $matchPos = $searchFrom + $m[0][1];
                    $candidateHeader = $matchPos - 14;

                    if ($candidateHeader >= $minCandidate
                        && $matchPos >= $minCandidate + 14
                        && $candidateHeader + 22 <= $blockLen
                        && preg_match($codePattern, substr($data, $candidateHeader + 14, 8))) {
                        $candidatePos = $candidateHeader;
                        break;
                    }

                    // Move one byte past this match and keep searching.
                    $searchFrom = $matchPos + 1;
                }

                if ($candidatePos === null) {
                    break;
                }

                $recordStart = $pos = $candidatePos;
                $code = substr($data, $pos + 14, 8);
                if (! preg_match($codePattern, $code)) {
                    break;
                }
            }

            $hdr = unpack('veNum/VpropTime/sslope/sloss/lrefl', substr($data, $pos, 14));
            if (! $hdr) {
                break;
            }

            $propTime = (int) $hdr['propTime'];

            // propTime is in 10^-4 microseconds (1e-10 s), one-way travel time.
            $distMeters = (self::SPEED_OF_LIGHT * ($propTime * self::PROP_TIME_UNIT)) / $groupIndex;

            $slopeDbKm = ((int) $hdr['slope']) * 0.001;
            $eventLossDb = ((int) $hdr['loss']) * 0.001;
            $reflectanceDb = ((int) $hdr['refl']) * 0.001; // 32-bit signed

            $code = trim(substr($data, $pos + 14, 8));
            $type = $this->describeEventType($code, $reflectanceDb);

            // Advance past code and its null-terminated comment.
            $cursor = $pos + 22;
            $comment = '';
            $nullPos = strpos($data, "\0", $cursor);
            if ($nullPos !== false && ($nullPos - $cursor) <= 512) {
                $comment = $this->cleanUtf8(substr($data, $cursor, $nullPos - $cursor));
                $cursor = $nullPos + 1;
            }

            $cumLoss += $eventLossDb;

            $events[] = [
                'eventNumber' => (int) $hdr['eNum'],
                'distanceKm' => round($distMeters / 1000, 4),
                'distanceMeters' => round($distMeters, 2),
                'propTime' => $propTime,
                'slopeDbKm' => round($slopeDbKm, 3),
                'eventLossDb' => round($eventLossDb, 3),
                'reflectanceDb' => round($reflectanceDb, 3),
                'cumulativeLossDb' => round($cumLoss, 3),
                'eventCode' => $code,
                'type' => $type,
                'comment' => $comment,
                'customTag' => '',
                'notes' => '',
            ];

            // A parsed record always spans at least 22 bytes (14-byte header +
            // 8-byte code). Advance to the end of the comment when present,
            // but never less than 22 bytes, so malformed blocks cannot stall.
            // The next resync window is measured from this record's header.
            $recordStart = $pos;
            $pos = max($cursor, $pos + 22);
        }

        return $events;
    }

    private function describeEventType(string $code, float $reflectanceDb): string
    {
        $marker = strlen($code) > 1 ? strtoupper($code[1]) : '';

        if ($marker === 'F') {
            return 'Front Connector / Launch';
        }
        if ($marker === 'E') {
            return 'Fiber End';
        }
        if ($reflectanceDb > -40.0) {
            return 'Reflective Event';
        }

        return 'Non-Reflective Event';
    }

    private function parseDataPts(string $b, ?array $block, array $fxd): array
    {
        if (! $block) {
            return [];
        }

        $blockStart = (int) $block['offset'];
        $length = strlen($b);
        if ($blockStart < 0 || $blockStart >= $length) {
            return [];
        }

        // Never read past the DataPts block boundary, only past the file end.
        $blockEnd = min($length, $blockStart + (int) $block['size']);
        if ($blockEnd - $blockStart < 9) {
            return [];
        } // at least "DataPts\0"

        $off = $blockStart;
        $this->readNullString($b, $off, $blockEnd); // Skip "DataPts"

        // Fixed header is 12 bytes and must fit inside the block.
        if ($off + 12 > $blockEnd) {
            return [];
        }

        $hdr = unpack('VtotPts/vnumTraces/VtracePts/vscale', substr($b, $off, 12));
        if ($hdr === false) {
            return [];
        }
        $off += 12;

        $ptsCount = (int) ($hdr['tracePts'] ?? 0);
        if ($ptsCount <= 0) {
            $ptsCount = (int) ($hdr['totPts'] ?? 0);
        }
        $scale = (int) ($hdr['scale'] ?? 1000);

        // Resolution from FxdParams (exact, un-rounded value).
        $spacingMeters = (float) ($fxd['sampleSpacingMetersExact'] ?? ($fxd['sampleSpacingMeters'] ?? 0));
        if ($spacingMeters < 0.001) {
            $spacingMeters = 0.0;
        }
        $spacingKm = $spacingMeters / 1000;

        // Raw point data is bounded by the DataPts block, not the whole file.
        $available = max(0, intdiv($blockEnd - $off, 2));
        $ptsCount = max(0, min($ptsCount, $available));

        $points = [];
        if ($ptsCount > 0) {
            $rawVals = unpack('v*', substr($b, $off, $ptsCount * 2));
            $factor = $scale * 0.000001;

            for ($i = 1; $i <= $ptsCount; $i++) {
                $raw = $rawVals[$i] ?? 65535;
                // 65535 = noise floor (no optical signal); lower raw = higher optical power.
                $db = round((65535 - $raw) * $factor, 3);

                $points[] = [
                    'x' => round(($i - 1) * $spacingKm, 8),
                    'y' => $db,
                ];
            }
        }

        return [
            'totalPoints' => count($points),
            'scaleFactor' => $scale,
            'spacingMeters' => round($spacingMeters, 6),
            'spacingKm' => $spacingKm,
            'points' => $points,
        ];
    }

    /**
     * Resolve a block's absolute start/end offsets, clamped to the buffer.
     *
     * @return array{0: int|null, 1: int|null} [start, end] or [null, null] if unusable
     */
    private function resolveBlockBounds(string $data, array $block): array
    {
        $length = strlen($data);
        $start = (int) ($block['offset'] ?? 0);
        $size = (int) ($block['size'] ?? 0);

        if ($start < 0 || $start >= $length || $size <= 0) {
            return [null, null];
        }

        $end = min($length, $start + $size);
        if ($end <= $start) {
            return [null, null];
        }

        return [$start, $end];
    }

    /**
     * Read a null-terminated string, never advancing past $maxOffset.
     * If no terminator exists before the limit, the read stops at the limit.
     */
    private function readNullString(string $data, int &$offset, ?int $maxOffset = null): string
    {
        $length = strlen($data);
        $limit = $maxOffset === null ? $length : min($length, $maxOffset);

        if ($offset < 0) {
            $offset = 0;
        }
        if ($offset >= $limit) {
            $offset = $limit;

            return '';
        }

        $nullPos = strpos($data, "\0", $offset);
        if ($nullPos === false || $nullPos >= $limit) {
            $str = substr($data, $offset, $limit - $offset);
            $offset = $limit;
        } else {
            $str = substr($data, $offset, $nullPos - $offset);
            $offset = $nullPos + 1;
        }

        return $this->cleanUtf8($str);
    }

    private function cleanUtf8(string $str): string
    {
        // Convert to safe valid UTF-8 string
        $converted = @mb_convert_encoding($str, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');

        // Remove control characters except standard space
        return trim(preg_replace('/[\x00-\x1F\x7F]/', '', $converted));
    }
}
