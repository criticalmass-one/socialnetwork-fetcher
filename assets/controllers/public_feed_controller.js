import { Controller } from '@hotwired/stimulus';

/*
 * Drives the public group feed page: infinite-scroll pagination against
 * /p/{slug}/more and the "… mehr" caption clamp toggle. Media gating happens
 * server-side, so this controller only deals with layout/interaction.
 */
export default class extends Controller {
    static targets = ['feed', 'sentinel'];
    static values = {
        url: String,
        page: Number,
        hasMore: Boolean,
    };

    connect() {
        this.loading = false;
        this.setupClamps(this.feedTarget);

        if (this.hasSentinelTarget && this.hasMoreValue) {
            this.observer = new IntersectionObserver(
                (entries) => entries.forEach((e) => e.isIntersecting && this.loadMore()),
                { rootMargin: '400px' },
            );
            this.observer.observe(this.sentinelTarget);
        }
    }

    disconnect() {
        if (this.observer) this.observer.disconnect();
    }

    // Reveal and play the local <video> in place of the Instagram cover link.
    playVideo(event) {
        event.preventDefault();
        const wrap = event.currentTarget.closest('.post-video');
        if (!wrap) return;

        const cover = wrap.querySelector('.video-cover');
        const button = wrap.querySelector('.video-play');
        const video = wrap.querySelector('video');

        if (cover) cover.hidden = true;
        if (button) button.hidden = true;
        if (video) {
            video.hidden = false;
            const play = video.play();
            if (play && typeof play.catch === 'function') play.catch(() => {});
        }
    }

    async loadMore() {
        if (this.loading || !this.hasMoreValue) return;
        this.loading = true;

        const nextPage = this.pageValue + 1;
        try {
            const res = await fetch(`${this.urlValue}?page=${nextPage}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);

            const html = await res.text();
            const tmp = document.createElement('div');
            tmp.innerHTML = html;

            const meta = tmp.querySelector('[data-more-meta]');
            const stillMore = meta ? meta.dataset.hasMore === '1' : false;
            if (meta) meta.remove();

            this.dedupeLeadingDayHeading(tmp);
            this.setupClamps(tmp);

            while (tmp.firstChild) this.feedTarget.appendChild(tmp.firstChild);

            this.pageValue = nextPage;
            this.hasMoreValue = stillMore;
            if (!stillMore && this.observer) this.observer.disconnect();
        } catch (e) {
            // Stop trying on error; the user can reload the page.
            if (this.observer) this.observer.disconnect();
            this.hasMoreValue = false;
        } finally {
            this.loading = false;
        }
    }

    // Drop a leading day heading in the freshly loaded fragment when it repeats
    // the last heading already visible (page boundary inside the same day).
    dedupeLeadingDayHeading(fragment) {
        const first = fragment.querySelector('.day-heading');
        if (!first) return;

        const existing = this.feedTarget.querySelectorAll('.day-heading');
        const last = existing[existing.length - 1];
        if (last && last.textContent.trim() === first.textContent.trim()) {
            first.remove();
        }
    }

    setupClamps(scope) {
        scope.querySelectorAll('.caption.clamp').forEach((cap) => {
            const more = cap.parentElement.querySelector('.more');
            if (!more) return;
            requestAnimationFrame(() => {
                if (cap.scrollHeight - cap.clientHeight > 4) {
                    more.hidden = false;
                    more.addEventListener('click', () => {
                        cap.classList.remove('clamp');
                        more.hidden = true;
                    });
                }
            });
        });
    }
}
