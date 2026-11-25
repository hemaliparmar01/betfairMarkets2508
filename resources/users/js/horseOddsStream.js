// Assemble one complete, versioned race book before exposing it to the UI.
export function createHorseOddsBook(accepts, apply) {
    const latest = new Map();
    const fragments = new Map();
    return {
        receive(packet) {
            if (!packet || !accepts(String(packet.id)) || !Number.isSafeInteger(packet.version) || packet.version < 1
                || !Number.isInteger(packet.parts) || packet.parts < 1 || packet.parts > 256
                || !Number.isInteger(packet.part) || packet.part < 0 || packet.part >= packet.parts || !Array.isArray(packet.runners)) return;
            const id = String(packet.id);
            if ((latest.get(id)?.version ?? 0) >= packet.version) return;
            let book = fragments.get(id);
            if (book && book.version > packet.version) return;
            if (!book || book.version !== packet.version) {
                book = { version: packet.version, parts: packet.parts, chunks: new Map() };
                fragments.set(id, book);
            }
            if (book.parts !== packet.parts) return;
            book.chunks.set(packet.part, packet.runners);
            if (book.chunks.size !== book.parts) return;
            const runners = Array.from({ length: book.parts }, (_, index) => book.chunks.get(index)).flat();
            const race = { ...packet, id, runners };
            latest.set(id, race); fragments.delete(id); apply(race);
        },
        replay() {
            for (const [id, race] of latest) {
                if (accepts(id)) apply(race); else { latest.delete(id); fragments.delete(id); }
            }
        },
    };
}

export function mergeHorseRunners(previous, incoming, sentAt) {
    const key = runner => `${runner.marketId}|${runner.marketType ?? 'WIN'}|${runner.runnerId}|${runner.handicap ?? 0}`;
    const old = new Map(previous.map(runner => [key(runner), runner]));
    return incoming.map(runner => {
        const before = old.get(key(runner));
        const merged = { ...before, ...runner };
        if (before && Number(before.currentBackOdds) !== Number(runner.currentBackOdds) && Number(runner.currentBackOdds) > 1) {
            merged.backOddsHistory = [...(before.backOddsHistory ?? []), { timestamp: sentAt, odds: runner.currentBackOdds }];
        }
        return merged;
    });
}

export function watchHorseOdds(book, raceIds) {
    const channels = new Map();
    function sync() {
        if (!window.Echo) return;
        const wanted = new Set(raceIds().map(String));
        for (const [id, name] of channels) {
            if (!wanted.has(id)) { window.Echo.leave(name); channels.delete(id); }
        }
        for (const id of wanted) {
            if (channels.has(id)) continue;
            const name = `sports.horseracing.odds.${id.replaceAll('.', '_')}`;
            channels.set(id, name);
            window.Echo.private(name).listen('.odds.updated', packet => { if (channels.has(id)) book.receive(packet); });
        }
    }
    if (window.Echo) sync(); else window.addEventListener('load', sync, { once: true });
    return sync;
}
