export function isDocumentHidden() {
    return document.visibilityState === 'hidden' || document.hidden;
}
