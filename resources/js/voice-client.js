export function createVoiceRuntime() {
    return {
        lkRoom: null,
        audioEls: {},
        micToggleInFlight: false,
        lastAppliedMicEnabled: null,
        permissionStream: null,
    };
}

export function stopPermissionStream(runtime) {
    if (!runtime.permissionStream) return;
    for (const track of runtime.permissionStream.getTracks()) {
        try { track.stop(); } catch (_) {}
    }
    runtime.permissionStream = null;
}
