package com.kankio.desktop

import android.os.Bundle
import androidx.activity.enableEdgeToEdge

import android.content.Intent
import android.os.Build

class MainActivity : TauriActivity() {
  override fun onCreate(savedInstanceState: Bundle?) {
    enableEdgeToEdge()
    super.onCreate(savedInstanceState)
    
    // Start Background Service for Audio and Mic
    val serviceIntent = Intent(this, BackgroundAudioService::class.java)
    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
        startForegroundService(serviceIntent)
    } else {
        startService(serviceIntent)
    }
  }
}
