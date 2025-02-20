<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class PythonController extends Controller
{
    public function runPythonScript(Request $request)
    {
        Log::info('API çağrısı alındı.');

        // JSON formatında zorunlu giriş kabul et
        $request->validate([
            'prompt' => 'required|string',
        ]);

        $prompt = $request->input('prompt');
        Log::info('Gelen prompt: '.$prompt);

        // Dosya adını benzersiz yap
        $fileName = 'prompt_'.time().'-'.Str::uuid().'.txt';
        $filePath = storage_path($fileName);
        Log::info('Prompt dosya adı: '.$fileName);

        // Dosyaya prompt'u kaydet
        file_put_contents($filePath, $prompt);
        Log::info('Prompt başarıyla kaydedildi: '.$filePath);

        // Python sanal ortam yolu ve script yolu
        $venvPath = base_path('/bots/chatgpt/myenv/bin/python');
        $scriptPath = base_path('/bots/chatgpt/main.py');

        Log::info('Python script çalıştırılıyor...');

        // Python scriptini çalıştır
        $process = new Process([$venvPath, $scriptPath, $filePath]);
        $process->run();

        // Hata kontrolü
        if (! $process->isSuccessful()) {
            Log::error('Python script hatası: '.$process->getErrorOutput());
            throw new ProcessFailedException($process);
        }

        // Python script çıktısını al
        $output = $process->getOutput();
        Log::info('Python script çıktısı: '.$output);

        // Çıktıdan HTML ve MD dosya adlarını ayıkla
        preg_match('/Yanıt kaydedildi:\s*(output_\d+\.html)/', $output, $htmlMatches);
        preg_match('/Dönüştürme tamamlandı:\s*(output_\d+\.md)/', $output, $mdMatches);

        $htmlFile = $htmlMatches[1] ?? null;
        $mdFile = $mdMatches[1] ?? null;

        if (! $htmlFile || ! $mdFile) {
            Log::error('Çıktı dosyaları bulunamadı! Çıktı: '.$output);

            return response()->json([
                'message' => 'Çıktı dosyaları bulunamadı.',
                'output' => $output,
            ], 500);
        }

        Log::info("Çıktı dosyaları bulundu: HTML = $htmlFile, MD = $mdFile");

        // Dosya yollarını belirle
        $htmlPath = base_path('/bots/chatgpt/'.$htmlFile);
        $mdPath = base_path('/bots/chatgpt/'.$mdFile);

        Log::info('HTML dosyası yolu: '.$htmlPath);
        Log::info('MD dosyası yolu: '.$mdPath);

        // HTML ve Markdown dosyalarının içeriğini oku
        $htmlContent = file_exists($htmlPath) ? file_get_contents($htmlPath) : null;
        $mdContent = file_exists($mdPath) ? file_get_contents($mdPath) : null;

        Log::info('Dönüştürme tamamlandı, yanıt gönderiliyor.');

        return response()->json([
            'message' => 'Python script çalıştırıldı.',
            'output_html_file' => $htmlFile,
            'output_md_file' => $mdFile,
            'html_content' => $htmlContent,
            'md_content' => $mdContent,
        ]);
    }
}
