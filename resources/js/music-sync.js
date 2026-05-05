export function musicStateHash(state) {
    return [
        state?.video_id || '',
        state?.is_playing ? '1' : '0',
        state?.started_at_unix || '',
        state?.position || 0,
        state?.queue?.length || 0,
    ].join(':');
}
