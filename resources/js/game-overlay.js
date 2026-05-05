export const GAME_EVENTS = Object.freeze({
    CLOSE: 'game:close',
    LOADED: 'game:loaded',
    FINISHED: 'game:session-finished',
    TOAST: 'game:toast',
    ERROR: 'game:error',
});

export function normalizeGameUrl(url) {
    const gameUrl = new URL(url, window.location.origin);
    gameUrl.searchParams.set('embedded', '1');

    return gameUrl.toString();
}

export function handleGameMessage(event, handlers = {}) {
    if (event.origin !== window.location.origin) return false;

    const type = event.data?.type;
    if (!type) return false;

    if (type === GAME_EVENTS.CLOSE || type === 'kankio:close-game') {
        handlers.close?.(event.data);
        return true;
    }
    if (type === GAME_EVENTS.LOADED) {
        handlers.loaded?.(event.data);
        return true;
    }
    if (type === GAME_EVENTS.FINISHED) {
        handlers.finished?.(event.data);
        return true;
    }
    if (type === GAME_EVENTS.TOAST || type === GAME_EVENTS.ERROR || type === 'kankio:toast') {
        handlers.toast?.(event.data);
        return true;
    }

    return false;
}
