<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BaglantiKalController extends Controller
{
    private string $jsonPath;

    public function __construct()
    {
        $this->jsonPath = storage_path('app/baglantikal.json');
    }

    public function index()
    {
        return view('pages.stay-connected', ['content' => $this->getContent()]);
    }

    public function save(Request $request)
    {
        $data = $request->validate([
            'muzik_id'            => 'nullable|string|max:50',
            'achievements'        => 'array|max:10',
            'achievements.*.icon' => 'string|max:10',
            'achievements.*.title'=> 'string|max:120',
            'achievements.*.desc' => 'string|max:300',
            'memories'            => 'array|max:8',
            'memories.*.icon'     => 'string|max:10',
            'memories.*.caption'  => 'string|max:120',
            'memories.*.detail'   => 'string|max:300',
            'memories.*.img'      => 'nullable|string|max:500',
            'boxes'               => 'array|max:6',
            'boxes.*.fi'          => 'string|max:10',
            'boxes.*.bi'          => 'string|max:10',
            'boxes.*.bt'          => 'string|max:120',
            'boxes.*.bc'          => 'string|max:500',
            'boxes.*.audio'       => 'nullable|string|max:500',
            'mektup'              => 'array',
            'mektup.p1'           => 'string|max:200',
            'mektup.p2'           => 'string|max:600',
            'mektup.p3'           => 'string|max:600',
            'mektup.p4'           => 'string|max:200',
        ]);

        file_put_contents(
            $this->jsonPath,
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );

        return response()->json(['ok' => true]);
    }

    public function uploadAudio(Request $request)
    {
        $request->validate([
            'audio' => 'required|file|mimes:webm,ogg,mp3,wav,mp4|max:10240',
        ]);

        $file     = $request->file('audio');
        $filename = 'bk_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $file->storeAs('baglantikal_audio', $filename, 'public');

        return response()->json([
            'audio_url' => '/storage/baglantikal_audio/' . $filename,
        ]);
    }

    private function getContent(): array
    {
        if (file_exists($this->jsonPath)) {
            $decoded = json_decode(file_get_contents($this->jsonPath), true);
            if ($decoded) return $decoded;
        }
        return $this->defaultContent();
    }

    private function defaultContent(): array
    {
        return [
            'muzik_id' => '',
            'achievements' => [
                ['icon' => '🌙', 'title' => 'Sabahlara Kadar Uyumayanlar',  'desc' => "Gece 03:00'de hâlâ sohbet ediyorsunuz."],
                ['icon' => '😂', 'title' => 'En Kötü Espri Ödülü',           'desc' => 'Bu kadar kötü şaka bile sanat sayılır.'],
                ['icon' => '🎮', 'title' => 'Dijital Silah Arkadaşı',         'desc' => 'Online savaş meydanlarında omuz omuza.'],
                ['icon' => '🤝', 'title' => 'Gerçek Dostluk',                 'desc' => 'Yıllar geçse de bağlantı kesilmez.'],
                ['icon' => '🎵', 'title' => 'Ortak Çalma Listesi',            'desc' => 'Aynı şarkıyı aynı anda dinlemek ayrı bir his.'],
                ['icon' => '💬', 'title' => 'Mesaj Ustası',                   'desc' => 'Binlerce mesaj, sonsuz anı.'],
            ],
            'memories' => [
                ['icon' => '📸', 'caption' => 'Ekran Görüntüsü #1',      'detail' => 'Yönetim panelinden görsel ekle', 'img' => null],
                ['icon' => '😂', 'caption' => 'Efsane Ekran Görüntüsü', 'detail' => 'O capsi hepimiz hatırlıyoruz',  'img' => null],
                ['icon' => '🎮', 'caption' => 'Oyun Anısı',               'detail' => 'İşte o zafer anı',             'img' => null],
                ['icon' => '💬', 'caption' => 'Unutulmaz Sohbet',         'detail' => 'Sabahın körüne kadar...',       'img' => null],
            ],
            'boxes' => [
                ['fi' => '🎁', 'bi' => '💌', 'bt' => 'Sesli Mesaj',        'bc' => 'Buraya sesli mesaj linki veya özel not ekle.', 'audio' => null],
                ['fi' => '📦', 'bi' => '🎂', 'bt' => 'Doğum Günü Mesajı', 'bc' => 'Seni tanımak bu dünyada bulduğum en güzel şeylerden biri.', 'audio' => null],
                ['fi' => '✉️', 'bi' => '🌟', 'bt' => 'İçten Şaka',          'bc' => 'Sadece senin anlayacağın o an... Biliyorsun hangisi 😂', 'audio' => null],
            ],
            'mektup' => [
                'p1' => 'Merhaba,',
                'p2' => 'Bu mektubu açabilmek için şifreyi bilmen gerekiyordu. Ve buldun.',
                'p3' => 'Seninle paylaştığımız her anı, her gülüşü ve sabahın körüne kadar uzayan sohbetleri çok değerli buluyorum.',
                'p4' => 'Doğum günün kutlu olsun. Hep bağlantıda kal.',
            ],
        ];
    }
}
