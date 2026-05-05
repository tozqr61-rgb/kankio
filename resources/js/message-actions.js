export async function safeJson(response, label) {
    const ct = response.headers.get('content-type') || '';
    if (!ct.includes('application/json')) {
        const text = await response.text().catch(() => '');
        throw new Error(`${label} JSON dönmedi (${response.status}). ${text.slice(0, 80)}`);
    }
    return response.json();
}
