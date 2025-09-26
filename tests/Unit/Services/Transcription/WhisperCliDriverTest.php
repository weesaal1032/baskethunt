<?php

namespace Tests\Unit\Services\Transcription;

use App\Services\Transcription\Drivers\WhisperCliDriver;
use Tests\TestCase;

class WhisperCliDriverTest extends TestCase
{
    public function test_transcribes_using_whisper_cli(): void
    {
        $audio = tempnam(sys_get_temp_dir(), 'audio-');
        $this->assertIsString($audio);
        file_put_contents($audio, 'audio');

        $script = tempnam(sys_get_temp_dir(), 'whisper-');
        $this->assertIsString($script);

        $scriptBody = <<<'BASH'
#!/bin/bash
input="$1"
shift
output_dir=""
while [[ "$#" -gt 0 ]]; do
  case "$1" in
    --output-dir)
      output_dir="$2"
      shift 2
      ;;
    *)
      shift
      ;;
  esac
done
name=$(basename "$input")
name="${name%.*}"
mkdir -p "$output_dir"
cat <<'JSON' > "$output_dir/$name.json"
{"text":"Hi there","segments":[{"start":0.0,"end":1.0,"text":"Hi"},{"start":1.0,"end":2.0,"text":"there"}]}
JSON
exit 0
BASH;

        file_put_contents($script, $scriptBody);
        chmod($script, 0755);

        $driver = new WhisperCliDriver([
            'binary' => $script,
            'model' => 'base.en',
            'threads' => 2,
            'timeout' => 10,
        ]);

        $result = $driver->transcribe($audio, [
            'language' => 'en',
        ]);

        $this->assertSame('Hi there', $result->text);
        $this->assertCount(2, $result->segments);
        $this->assertNull($result->confidence);
        $this->assertSame('en', $result->language);

        @unlink($audio);
        @unlink($script);
    }
}
