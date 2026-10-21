<?php

namespace Tests\Feature;

use App\Services\SorParserService;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OtdrTest extends TestCase
{
    private const SPEED_OF_LIGHT = 299792458.0;

    /**
     * Test index dashboard returns status 200.
     */
    public function test_dashboard_page_loads_successfully(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('OTDR Viewer', false);
        $response->assertSee('SR-4731', false);
    }

    /**
     * Test sample endpoint loads Doc/kabel1 cbd c1.sor properly.
     */
    public function test_sample_endpoint_returns_valid_parsed_trace(): void
    {
        $response = $this->get('/api/otdr/sample');
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $data = $response->json();
        $this->assertArrayHasKey('trace', $data);
        $this->assertEquals('FIBERCLOUD', $data['trace']['summary']['supplier']);
        $this->assertEquals('FC4000', $data['trace']['summary']['model']);

        // Total distance = last event distance, not the instrument range.
        $this->assertEqualsWithDelta(1.3691, $data['trace']['summary']['totalDistanceKm'], 0.0005);

        $this->assertEquals(1310, $data['trace']['summary']['wavelength']);
        $this->assertEqualsWithDelta(1.4677, $data['trace']['summary']['ior'], 0.00001);
        $this->assertCount(2, $data['trace']['events']);
        $this->assertCount(16384, $data['trace']['dataPoints']['points']);
    }

    /**
     * Sample spacing must be derived from c and group index (SR-4731), not decimal-shifted.
     */
    public function test_sample_spacing_matches_physical_calculation(): void
    {
        $parsed = (new SorParserService)->parse(base_path('Doc/kabel1 cbd c1.sor'));
        $fxd = $parsed['metadata']['fixed'];

        $this->assertSame(62502, $fxd['sampleSpacingRaw']);

        $expected = (self::SPEED_OF_LIGHT * (62502 * 1e-14)) / 1.4677; // 0.1276666 m
        $this->assertEqualsWithDelta($expected, $fxd['sampleSpacingMeters'], 1e-6);
        $this->assertEqualsWithDelta(0.127667, $fxd['sampleSpacingMeters'], 1e-6);

        // Old buggy value was 0.625 m.
        $this->assertLessThan(0.2, $fxd['sampleSpacingMeters']);
        $this->assertEqualsWithDelta($expected / 1000, $parsed['dataPoints']['spacingKm'], 1e-9);
    }

    /**
     * Event distances must use propTime (1e-10 s) scaled by c and group index.
     */
    public function test_event_distances_use_propagation_time_formula(): void
    {
        $parsed = (new SorParserService)->parse(base_path('Doc/kabel1 cbd c1.sor'));
        $events = $parsed['events'];

        $this->assertCount(2, $events);
        $this->assertEquals(1, $events[0]['eventNumber']);
        $this->assertSame('1F9999LS', $events[0]['eventCode']);
        $this->assertEqualsWithDelta(0.0, $events[0]['distanceMeters'], 0.001);

        $this->assertEquals(2, $events[1]['eventNumber']);
        $this->assertSame('1E9999LS', $events[1]['eventCode']);
        $this->assertSame(67027, $events[1]['propTime']);

        $expectedMeters = (self::SPEED_OF_LIGHT * (67027 * 1e-10)) / 1.4677;
        $this->assertEqualsWithDelta($expectedMeters, $events[1]['distanceMeters'], 0.01);
        $this->assertEqualsWithDelta(1.3691, $events[1]['distanceKm'], 0.0005);
    }

    /**
     * Reflectance must be parsed as 32-bit signed values (negative dB).
     */
    public function test_reflectance_is_signed_and_negative(): void
    {
        $parsed = (new SorParserService)->parse(base_path('Doc/kabel1 cbd c1.sor'));

        $this->assertEqualsWithDelta(-55.507, $parsed['events'][0]['reflectanceDb'], 0.001);
        $this->assertEqualsWithDelta(-43.927, $parsed['events'][1]['reflectanceDb'], 0.001);

        foreach ($parsed['events'] as $event) {
            $this->assertLessThan(0, $event['reflectanceDb'], 'Reflectance must be negative dB.');
        }
    }

    /**
     * Curve must decline toward noise floor, with a reflection spike at the fiber end.
     */
    public function test_trace_curve_declines_and_has_reflection_spike(): void
    {
        $parsed = (new SorParserService)->parse(base_path('Doc/kabel1 cbd c1.sor'));
        $points = $parsed['dataPoints']['points'];

        $first = $points[0];
        $last = end($points);

        $this->assertEqualsWithDelta(10.064, $first['y'], 0.01);
        $this->assertEqualsWithDelta(0.0, $last['y'], 0.01);

        $peak = ['y' => -1.0];
        foreach ($points as $point) {
            if ($point['y'] > $peak['y']) {
                $peak = $point;
            }
        }

        $this->assertEqualsWithDelta(23.507, $peak['y'], 0.01);
        $this->assertEqualsWithDelta($parsed['events'][1]['distanceKm'], $peak['x'], 0.01);

        // X axis must span the acquisition window, not 16x it.
        $this->assertEqualsWithDelta(
            $parsed['metadata']['fixed']['spanKm'],
            $last['x'],
            0.001
        );
        $this->assertLessThan(2.2, $last['x']);
    }

    /**
     * Power budget uses cumulative loss to derive remaining RX power and margin.
     */
    public function test_power_budget_formulas_and_pass_fail_threshold(): void
    {
        $txPower = -2.0;
        $cumulativeLoss = 3.5;
        $rxSensitivity = -8.0;

        $remainingPower = $txPower - $cumulativeLoss;
        $margin = $remainingPower - $rxSensitivity;

        $this->assertEqualsWithDelta(-5.5, $remainingPower, 0.0001);
        $this->assertEqualsWithDelta(2.5, $margin, 0.0001);
        $this->assertTrue($remainingPower >= $rxSensitivity);
        $this->assertFalse((-8.1) >= $rxSensitivity);
    }

    /**
     * Sample key events preserve cumulative loss and remaining power calculations.
     */
    public function test_sample_key_events_calculate_cumulative_loss_and_remaining_power(): void
    {
        $parsed = (new SorParserService)->parse(base_path('Doc/kabel1 cbd c1.sor'));
        $events = $parsed['events'];
        $txPower = -2.0;
        $rxSensitivity = -8.0;

        $this->assertCount(2, $events);
        $this->assertSame(0.0, $events[0]['cumulativeLossDb']);
        $this->assertSame(0.0, $events[1]['cumulativeLossDb']);

        foreach ($events as $event) {
            $remainingPower = $txPower - $event['cumulativeLossDb'];
            $margin = $remainingPower - $rxSensitivity;

            $this->assertEqualsWithDelta(-2.0, $remainingPower, 0.0001);
            $this->assertEqualsWithDelta(6.0, $margin, 0.0001);
            $this->assertTrue($remainingPower >= $rxSensitivity);
        }
    }

    /**
     * Nonzero loss changes RX power and pass/fail margin correctly.
     */
    #[DataProvider('powerBudgetScenarios')]
    public function test_nonzero_loss_power_budget_scenarios(
        float $cumulativeLoss,
        float $expectedRxPower,
        float $expectedMargin,
        bool $expectedPass
    ): void {
        $txPower = 7.0;
        $sensitivity = -27.0;

        $rxPower = $txPower - $cumulativeLoss;
        $margin = $rxPower - $sensitivity;

        $this->assertEqualsWithDelta($expectedRxPower, $rxPower, 0.0001);
        $this->assertEqualsWithDelta($expectedMargin, $margin, 0.0001);
        $this->assertSame($expectedPass, $margin >= 0.0);
    }

    /**
     * @return array<string, array{float, float, float, bool}>
     */
    public static function powerBudgetScenarios(): array
    {
        return [
            'small loss passes' => [2.450, 4.550, 31.550, true],
            'large loss fails' => [35.800, -28.800, -1.800, false],
            'sensitivity boundary passes' => [34.000, -27.000, 0.000, true],
        ];
    }

    public function test_sample_and_synthetic_events_parse_nonzero_loss_accurately(): void
    {
        $sample = (new SorParserService)->parse(base_path('Doc/kabel1 cbd c1.sor'));
        $this->assertCount(2, $sample['events']);
        foreach ($sample['events'] as $event) {
            $this->assertArrayHasKey('eventLossDb', $event);
            $this->assertArrayHasKey('cumulativeLossDb', $event);
        }

        $synthetic = $this->buildSor([
            'FxdParams' => $this->buildFxdParamsBlock(),
            'KeyEvents' => "KeyEvents\0".pack('v', 2)
                .$this->buildKeyEventRecord(1, 0, '1F9999LS')
                .$this->buildKeyEventRecord(2, 67027, '1E9999LS'),
        ]);

        $events = (new SorParserService)->parse($synthetic, false)['events'];
        $this->assertCount(2, $events);
        $this->assertSame(0.250, $events[0]['eventLossDb']);
        $this->assertSame(0.250, $events[0]['cumulativeLossDb']);
        $this->assertSame(0.250, $events[1]['eventLossDb']);
        $this->assertSame(0.500, $events[1]['cumulativeLossDb']);
    }

    /**
     * Test upload parse endpoint with valid .sor file.
     */
    public function test_parse_endpoint_processes_uploaded_sor_file(): void
    {
        $samplePath = base_path('Doc/kabel1 cbd c1.sor');
        $this->assertFileExists($samplePath);

        $uploadedFile = new UploadedFile(
            $samplePath,
            'test_kabel.sor',
            'application/octet-stream',
            null,
            true
        );

        $response = $this->post('/api/otdr/parse', [
            'files' => [$uploadedFile],
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'count' => 1,
        ]);

        $data = $response->json();
        $this->assertEquals('test_kabel.sor', $data['traces'][0]['fileName']);
        $this->assertEquals('FC4000', $data['traces'][0]['summary']['model']);
        $this->assertCount(2, $data['traces'][0]['events']);
    }

    /**
     * Build a synthetic SR-4731 file from a block map.
     *
     * @param  array<string, string>  $blocks  Block ID => full block payload (including its name prefix)
     */
    private function buildSor(array $blocks, string $mapName = 'Map'): string
    {
        $descriptors = '';
        foreach ($blocks as $name => $content) {
            $descriptors .= $name."\0".pack('vV', 200, strlen($content));
        }

        $mapSize = 12 + strlen($descriptors);
        $map = $mapName."\0".pack('v', 200).pack('V', $mapSize).pack('v', count($blocks) + 1).$descriptors;

        $file = $map.implode('', array_values($blocks));

        if (strlen($file) < 64) {
            $file .= str_repeat("\0", 64 - strlen($file));
        }

        return $file;
    }

    private function buildFxdParamsBlock(): string
    {
        return "FxdParams\0".pack('V', 1700000000)
            .pack('a2', 'km')
            .pack('v', 13100)
            .pack('V', 1)
            .pack('V', 0)
            .pack('v', 1)
            .pack('v', 10)
            .pack('V', 62502)
            .pack('V', 100)
            .pack('V', 146770)
            .pack('v', 500)
            .pack('v', 1)
            .pack('V', 0)
            .pack('V', 20000);
    }

    public function test_parser_rejects_invalid_magic_header(): void
    {
        $this->expectException(\Exception::class);

        (new SorParserService)->parse(str_repeat('Z', 128), false);
    }

    public function test_parser_rejects_file_shorter_than_minimum(): void
    {
        $this->expectException(\Exception::class);

        (new SorParserService)->parse("Map\0", false);
    }

    public function test_parser_rejects_truncated_real_sample(): void
    {
        $sample = file_get_contents(base_path('Doc/kabel1 cbd c1.sor'));
        $this->assertIsString($sample);

        $this->expectException(\Exception::class);

        (new SorParserService)->parse(substr($sample, 0, 200), false);
    }

    public function test_parser_rejects_block_descriptor_pointing_past_file_end(): void
    {
        $descriptors = "DataPts\0".pack('vV', 200, 0xFFFFFFF0);
        $mapSize = 12 + strlen($descriptors);
        $file = "Map\0".pack('v', 200).pack('V', $mapSize).pack('v', 2).$descriptors;
        $file = str_pad($file, 128, "\0");

        $this->expectException(\Exception::class);

        (new SorParserService)->parse($file, false);
    }

    public function test_parser_rejects_truncated_block_descriptor(): void
    {
        // Descriptor name ends with only 4 bytes left before the file end,
        // so the 6-byte descriptor header cannot be read.
        $descriptors = str_repeat('X', 47)."\0";
        $mapSize = 12 + strlen($descriptors); // 60
        $declaredSize = 64;                   // 4 bytes of slack in the file
        $file = "Map\0".pack('v', 200).pack('V', $declaredSize).pack('v', 2).$descriptors;
        $file = str_pad($file, $declaredSize, "\0");

        $this->assertSame(64, strlen($file));

        $this->expectException(\Exception::class);

        (new SorParserService)->parse($file, false);
    }

    public function test_datapoints_are_bounded_by_block_size_not_file_size(): void
    {
        $dataPts = "DataPts\0"
            .pack('V', 100)   // total points declared
            .pack('v', 1)     // num traces
            .pack('V', 100)   // points per trace declared
            .pack('v', 1000)  // scale
            .str_repeat(pack('v', 60000), 10); // only 10 points actually stored

        $file = $this->buildSor([
            'FxdParams' => $this->buildFxdParamsBlock(),
            'DataPts' => $dataPts,
        ]);

        $parsed = (new SorParserService)->parse($file, false);

        $this->assertSame(10, $parsed['dataPoints']['totalPoints']);
        $this->assertCount(10, $parsed['dataPoints']['points']);
    }

    public function test_keyevents_without_valid_codes_terminates_safely(): void
    {
        $keyEvents = "KeyEvents\0".pack('v', 5).str_repeat('X', 64);

        $file = $this->buildSor([
            'FxdParams' => $this->buildFxdParamsBlock(),
            'KeyEvents' => $keyEvents,
        ]);

        $parsed = (new SorParserService)->parse($file, false);

        $this->assertSame([], $parsed['events']);
    }

    /**
     * Build a single KeyEvents record: 14-byte header + 8-byte code + comment.
     */
    private function buildKeyEventRecord(
        int $eventNumber,
        int $propTime,
        string $code,
        string $comment = ''
    ): string {
        return pack('v', $eventNumber)
            .pack('V', $propTime)
            .pack('s', -100)      // slope
            .pack('s', 250)       // loss
            .pack('l', -40000)    // reflectance
            .$code
            .$comment."\0";
    }

    /**
     * Resync must advance strictly: a candidate header may not land inside the
     * 22-byte fixed span (14-byte header + 8-byte code) of the record being
     * replaced. A decoy code early in the gap is rejected and skipped.
     */
    public function test_keyevents_resync_ignores_candidate_inside_current_record_span(): void
    {
        // No valid record at $pos = 12, so resync starts with $recordStart = 12
        // and only accepts headers at offset >= 34.
        // Decoy code at 47 implies a header at 33 = $recordStart + 21, i.e. the
        // last byte still inside the forbidden [$recordStart, $recordStart + 21]
        // span. It must be rejected and skipped.
        $junk = str_repeat('X', 35);                 // 12..46
        $decoy = '9Z1234AB';                         // code 47..54 -> header 33
        $padding = str_repeat('X', 60 - 55);         // 55..59
        $real = $this->buildKeyEventRecord(2, 67027, '1E9999LS', 'end');

        $keyEvents = "KeyEvents\0".pack('v', 2).$junk.$decoy.$padding.$real;

        $file = $this->buildSor([
            'FxdParams' => $this->buildFxdParamsBlock(),
            'KeyEvents' => $keyEvents,
        ]);

        $parsed = (new SorParserService)->parse($file, false);
        $events = $parsed['events'];

        $this->assertCount(1, $events);
        $this->assertSame(2, $events[0]['eventNumber']);
        $this->assertSame('1E9999LS', $events[0]['eventCode']);
        $this->assertSame('end', $events[0]['comment']);

        foreach ($events as $event) {
            $this->assertNotSame('9Z1234AB', $event['eventCode']);
        }
    }

    /**
     * A candidate header exactly at $recordStart + 22 is the first legal
     * position and must be accepted.
     */
    public function test_keyevents_resync_accepts_candidate_at_strict_advance_boundary(): void
    {
        // Junk at $pos = 12 forces resync; event header sits at 34 (= 12 + 22).
        $junk = str_repeat('X', 22);                 // 12..33
        $real = $this->buildKeyEventRecord(2, 67027, '1E9999LS', 'end');

        $keyEvents = "KeyEvents\0".pack('v', 1).$junk.$real;

        $file = $this->buildSor([
            'FxdParams' => $this->buildFxdParamsBlock(),
            'KeyEvents' => $keyEvents,
        ]);

        $parsed = (new SorParserService)->parse($file, false);
        $events = $parsed['events'];

        $this->assertCount(1, $events);
        $this->assertSame(2, $events[0]['eventNumber']);
        $this->assertSame('1E9999LS', $events[0]['eventCode']);
        $this->assertSame('end', $events[0]['comment']);
    }

    /**
     * A block packed with pattern-like bytes must terminate and stay bounded.
     */
    public function test_keyevents_pattern_garbage_terminates_without_infinite_loop(): void
    {
        $keyEvents = "KeyEvents\0".pack('v', 0).str_repeat('9Z1234AB', 64);

        $file = $this->buildSor([
            'FxdParams' => $this->buildFxdParamsBlock(),
            'KeyEvents' => $keyEvents,
        ]);

        $parsed = (new SorParserService)->parse($file, false);

        // Producing some events is acceptable for ambiguous bytes, but the
        // parse must finish and cannot exceed what the block can physically hold.
        $this->assertLessThanOrEqual(64, count($parsed['events']));
        foreach ($parsed['events'] as $event) {
            $this->assertIsInt($event['eventNumber']);
            $this->assertGreaterThanOrEqual(0.0, $event['distanceMeters']);
        }
    }

    public function test_truncated_fxdparams_header_returns_empty_metadata(): void
    {
        // "FxdParams\0" + 20 bytes of a 44-byte fixed record: too short.
        $fxdParams = "FxdParams\0".str_repeat("\0", 20);

        $file = $this->buildSor([
            'FxdParams' => $fxdParams,
        ]);

        $parsed = (new SorParserService)->parse($file, false);

        $this->assertSame([], $parsed['metadata']['fixed']);
        $this->assertSame(1310, $parsed['summary']['wavelength']);
    }

    public function test_truncated_datapoints_header_is_bounded(): void
    {
        // "DataPts\0" + 6 bytes: the 12-byte header cannot be read.
        $dataPts = "DataPts\0".str_repeat("\0", 6);

        $file = $this->buildSor([
            'FxdParams' => $this->buildFxdParamsBlock(),
            'DataPts' => $dataPts,
        ]);

        $parsed = (new SorParserService)->parse($file, false);

        $this->assertSame([], $parsed['dataPoints']);
    }

    public function test_parser_rejects_wrapped_block_size(): void
    {
        // 0xFFFFFFFF overflows a signed 32-bit read and must be rejected
        // instead of producing a negative/wrapped block size.
        $descriptors = "DataPts\0".pack('vV', 200, 0xFFFFFFFF);
        $mapSize = 12 + strlen($descriptors);
        $file = "Map\0".pack('v', 200).pack('V', $mapSize).pack('v', 2).$descriptors;
        $file = str_pad($file, 128, "\0");

        $this->expectException(\Exception::class);

        (new SorParserService)->parse($file, false);
    }

    public function test_genparams_without_terminator_is_bounded_by_block_size(): void
    {
        $genParams = "GenParams\0".str_repeat('A', 32); // no null terminator

        $file = $this->buildSor([
            'GenParams' => $genParams,
        ]);

        $parsed = (new SorParserService)->parse($file, false);

        $this->assertArrayHasKey('general', $parsed['metadata']);
        $this->assertSame(str_repeat('A', 32), $parsed['metadata']['general']['languageCode']);
        $this->assertSame(1, count($parsed['metadata']['general']['allStrings']));
    }

    public function test_supparams_without_terminator_does_not_leak_past_block(): void
    {
        $supParams = "SupParams\0".'VENDOR'."\0".'MODEL'."\0".str_repeat('S', 24);

        $file = $this->buildSor([
            'SupParams' => $supParams,
        ]);

        $parsed = (new SorParserService)->parse($file, false);

        $this->assertSame('VENDOR', $parsed['metadata']['supplier']['supplier']);
        $this->assertSame('MODEL', $parsed['metadata']['supplier']['model']);
        $this->assertSame(str_repeat('S', 24), $parsed['metadata']['supplier']['serialNumber']);
        $this->assertSame('', $parsed['metadata']['supplier']['module']);
    }

    /**
     * Vendor reference sample: event 2 at 1.228 km, reflectance -53.812 / -46.611 dB.
     * Skipped when the external reference file is unavailable.
     */
    public function test_reference_sample_matches_vendor_software_values(): void
    {
        $reference = 'D:/Kerjaan/Hasil OTDR Kabel/Kabel CBD/KABEL-2/kabel2 cbd c1.sor';
        if (! file_exists($reference)) {
            $this->markTestSkipped('Reference vendor .sor file not available on this machine.');
        }

        $parsed = (new SorParserService)->parse($reference);

        $this->assertCount(2, $parsed['events']);
        $this->assertEqualsWithDelta(1.2279, $parsed['events'][1]['distanceKm'], 0.0005);
        $this->assertEqualsWithDelta(-53.812, $parsed['events'][0]['reflectanceDb'], 0.001);
        $this->assertEqualsWithDelta(-46.611, $parsed['events'][1]['reflectanceDb'], 0.001);
        $this->assertEqualsWithDelta(1.2279, $parsed['summary']['totalDistanceKm'], 0.0005);
    }
}
