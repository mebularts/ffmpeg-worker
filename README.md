# Samplitter FFmpeg Worker

Bu klasör GitHub üzerinden çalışacak basit bir FFmpeg işleyicisi içerir. `worker.php`, ".pending" uzantılı JSON iş dosyalarını izler ve her birine `ffmpeg` veya `ffprobe` komutları çalıştırarak yanıt üretir.

## Kullanım

1. GitHub Actions gibi bir ortamda `php worker.php jobs results` komutunu sürekli çalıştır.
2. `jobs/` dizinine aşağıdaki gibi bir JSON dosyası bırak:

```json
{
  "id": "job-123",
  "action": "convert",
  "input": "/tmp/input.mp4",
  "output": "/tmp/output.mp3",
  "format": "mp3"
}
```

3. Worker dosyayı `.pending` uzantısı ile algılar, çalıştırır ve sonuçları `results/<id>.result.json` içinde tutar.
4. PHP API (örneğin `php-api/api.php`) bu sonucu okuyup kendi `storage`’ına kopyalayabilir ya da kullanıcıya sunabilir.

## Ortam Değişkenleri

- `FFMPEG_BIN`: FFmpeg ikilisi (`/usr/bin/ffmpeg`) veya depoladığınız başka bir konum.
- `FFPROBE_BIN`: FFprobe ikilisi.

Çalışma dizinine göre `jobs/` ve `results/` klasörleri otomatik yaratılır.
