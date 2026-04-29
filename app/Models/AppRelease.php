<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppRelease extends Model
{
    protected $fillable = ['version', 'notes', 'drive_link'];

    /**
     * Otomatik olarak Google Drive linkini doğrudan indirme (direct download) linkine çevirir.
     */
    public function getDirectDownloadLinkAttribute()
    {
        $link = $this->drive_link;
        if (preg_match('/\/file\/d\/([a-zA-Z0-9_-]+)/', $link, $matches)) {
            $fileId = $matches[1];
            return "https://drive.google.com/uc?export=download&id={$fileId}";
        }
        return $link;
    }
}
