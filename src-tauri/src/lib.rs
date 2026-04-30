use tauri::Manager;

#[cfg(desktop)]
use tauri::{
    menu::{Menu, MenuItem, PredefinedMenuItem},
    tray::{MouseButton, MouseButtonState, TrayIconBuilder, TrayIconEvent},
    AppHandle, WindowEvent,
};

// ─────────────────────────────────────────────────────────────────────────────
// Entry point called from main.rs
// ─────────────────────────────────────────────────────────────────────────────
#[cfg_attr(mobile, tauri::mobile_entry_point)]
pub fn run() {
    let mut builder = tauri::Builder::default()
        .plugin(tauri_plugin_shell::init())
        .plugin(tauri_plugin_notification::init());

    #[cfg(desktop)]
    {
        builder = builder.plugin(tauri_plugin_global_shortcut::Builder::new().build());
    }

    builder
        .setup(|app| {
            #[cfg(desktop)]
            {
                build_tray(app)?;
                register_shortcuts(app)?;
            }
            inject_performance_tweaks(app)?;
            allow_media_permissions(app);
            Ok(())
        })
        .on_window_event(|_window, _event| {
            #[cfg(desktop)]
            {
                if let WindowEvent::CloseRequested { api, .. } = _event {
                    api.prevent_close();
                    let _ = _window.hide();
                }
            }
        })
        .run(tauri::generate_context!())
        .expect("Kankio başlatılamadı");
}

// ─────────────────────────────────────────────────────────────────────────────
// Performance: inject JS tweaks into WebView after page load
// ─────────────────────────────────────────────────────────────────────────────
fn inject_performance_tweaks(app: &mut tauri::App) -> tauri::Result<()> {
    if let Some(win) = app.get_webview_window("main") {
        let _ = win.eval(r#"
            /* Mark as desktop app so the web layer can skip PWA install prompts */
            window.__KANKIO_DESKTOP__ = true;

            /* Reduce idle timer strain — when window is hidden, pause non-critical polls */
            document.addEventListener('visibilitychange', () => {
                if (document.hidden && window._chatLayout) {
                    window._chatLayout._hidden = true;
                }
            });
        "#);
    }
    Ok(())
}

// ─────────────────────────────────────────────────────────────────────────────
// System Tray (Desktop Only)
// ─────────────────────────────────────────────────────────────────────────────
#[cfg(desktop)]
fn build_tray(app: &mut tauri::App) -> tauri::Result<()> {
    let show_item = MenuItem::with_id(app, "show", "Odaya Dön", true, None::<&str>)?;
    let sep       = PredefinedMenuItem::separator(app)?;
    let quit_item = MenuItem::with_id(app, "quit", "Çıkış",     true, None::<&str>)?;

    let menu = Menu::with_items(app, &[&show_item, &sep, &quit_item])?;

    TrayIconBuilder::new()
        .icon(app.default_window_icon().unwrap().clone())
        .menu(&menu)
        .tooltip("Kankio — Sesli Sohbet & Müzik")
        .show_menu_on_left_click(false)
        .on_menu_event(|app, event| match event.id.as_ref() {
            "show" => bring_to_front(app),
            "quit" => app.exit(0),
            _      => {}
        })
        .on_tray_icon_event(|tray, event| {
            if let TrayIconEvent::Click {
                button: MouseButton::Left,
                button_state: MouseButtonState::Up,
                ..
            } = event
            {
                bring_to_front(tray.app_handle());
            }
        })
        .build(app)?;

    Ok(())
}

/// Show, restore and focus the main window.
#[cfg(desktop)]
fn bring_to_front(app: &AppHandle) {
    if let Some(win) = app.get_webview_window("main") {
        let _ = win.show();
        let _ = win.unminimize();
        let _ = win.set_focus();
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Global Shortcuts (Desktop Only)
// ─────────────────────────────────────────────────────────────────────────────
#[cfg(desktop)]
fn register_shortcuts(app: &mut tauri::App) -> tauri::Result<()> {
    use tauri_plugin_global_shortcut::{Code, GlobalShortcutExt, Modifiers, Shortcut};

    // Alt + M  →  mute/unmute toggle
    let mute = Shortcut::new(Some(Modifiers::ALT), Code::KeyM);
    let _ = app.global_shortcut().on_shortcut(mute, |app, _, _| {
        if let Some(win) = app.get_webview_window("main") {
            let _ = win.eval(
                "window.dispatchEvent(new CustomEvent('tauri-mute-toggle'))"
            );
        }
    });

    // Alt + K  →  bring window to front
    let focus = Shortcut::new(Some(Modifiers::ALT), Code::KeyK);
    let _ = app.global_shortcut().on_shortcut(focus, |app, _, _| {
        bring_to_front(app);
    });

    Ok(())
}

// ─────────────────────────────────────────────────────────────────────────────
// WebKit2GTK: auto-grant only user-media capture required by voice chat.
// ─────────────────────────────────────────────────────────────────────────────
fn allow_media_permissions(app: &mut tauri::App) {
    #[cfg(target_os = "linux")]
    if let Some(win) = app.get_webview_window("main") {
        let _ = win.with_webview(|webview| {
            use webkit2gtk::prelude::*;
            use webkit2gtk::PermissionRequestExt;
            if let Some(wk) = webview.inner().dynamic_cast_ref::<webkit2gtk::WebView>() {
                wk.connect_permission_requested(|_, request| {
                    if request.is::<webkit2gtk::UserMediaPermissionRequest>() {
                        request.allow();
                        return glib::signal::Inhibit(true);
                    }
                    glib::signal::Inhibit(false)
                });
            }
        });
    }
}
