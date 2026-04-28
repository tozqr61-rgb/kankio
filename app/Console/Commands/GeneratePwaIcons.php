<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GeneratePwaIcons extends Command
{
    protected $signature   = 'pwa:icons';
    protected $description = 'Generate PWA PNG icons (192×192 and 512×512) using PHP GD';

    public function handle(): int
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->error('PHP GD extension is not enabled. Enable it in php.ini and restart.');
            return self::FAILURE;
        }

        $dir = public_path('icons');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        foreach ([192, 512] as $size) {
            $this->generateIcon($size, $dir);
            $this->info("✓ Generated icon-{$size}.png");
        }

        $this->info('PWA icons saved to public/icons/');
        return self::SUCCESS;
    }

    private function generateIcon(int $size, string $dir): void
    {
        $img   = imagecreatetruecolor($size, $size);
        $bg    = imagecolorallocate($img, 9, 9, 11);      /* zinc-950   */
        $green = imagecolorallocate($img, 52, 211, 153);  /* emerald-400 */
        $dim   = imagecolorallocate($img, 16, 56, 40);    /* dark green  */

        /* — Background — */
        imagefill($img, 0, 0, $bg);

        /* — Rounded corners via filling a rounded square — */
        $r = (int)($size * 0.18);
        $this->filledRoundedRect($img, 0, 0, $size - 1, $size - 1, $r, $bg, $green, $dim, $size);

        /* — Letter K — */
        $s = $size / 512.0;

        /* Vertical bar */
        $this->filledRect($img, $s, 148, 112, 148 + 44, 112 + 288, $green);

        /* Upper arm of K */
        $this->filledTriangle($img, $s, 192, 256, 320, 112, 364, 112, $green);
        $this->filledTriangle($img, $s, 192, 256, 236, 256, 364, 112, $green);

        /* Lower arm of K */
        $this->filledTriangle($img, $s, 192, 256, 236, 256, 364, 400, $green);
        $this->filledTriangle($img, $s, 192, 256, 320, 400, 364, 400, $green);

        /* — Save — */
        imagepng($img, "{$dir}/icon-{$size}.png");
        imagedestroy($img);
    }

    private function filledRect($img, float $s, int $x1, int $y1, int $x2, int $y2, $color): void
    {
        imagefilledrectangle($img, (int)($x1 * $s), (int)($y1 * $s), (int)($x2 * $s), (int)($y2 * $s), $color);
    }

    private function filledTriangle($img, float $s, int $ax, int $ay, int $bx, int $by, int $cx, int $cy, $color): void
    {
        imagefilledpolygon($img, [
            (int)($ax * $s), (int)($ay * $s),
            (int)($bx * $s), (int)($by * $s),
            (int)($cx * $s), (int)($cy * $s),
        ], $color);
    }

    private function filledRoundedRect($img, int $x1, int $y1, int $x2, int $y2, int $r, $bg, $green, $dim, int $size): void
    {
        /* Fill main rect minus corners */
        imagefilledrectangle($img, $x1 + $r, $y1, $x2 - $r, $y2, $bg);
        imagefilledrectangle($img, $x1, $y1 + $r, $x2, $y2 - $r, $bg);
        /* Rounded corner fills */
        imagefilledellipse($img, $x1 + $r, $y1 + $r, 2 * $r, 2 * $r, $bg);
        imagefilledellipse($img, $x2 - $r, $y1 + $r, 2 * $r, 2 * $r, $bg);
        imagefilledellipse($img, $x1 + $r, $y2 - $r, 2 * $r, 2 * $r, $bg);
        imagefilledellipse($img, $x2 - $r, $y2 - $r, 2 * $r, 2 * $r, $bg);

        /* Subtle green ring */
        imagesetthickness($img, max(2, (int)($size / 96)));
        imagearc($img, (int)($size / 2), (int)($size / 2), (int)($size * 0.92), (int)($size * 0.92), 0, 360, $dim);
    }
}
