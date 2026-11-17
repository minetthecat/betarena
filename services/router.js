document.addEventListener("DOMContentLoaded", () => {
    const content = document.getElementById("content");
    let requestId = 0; // ignore outdated responses

    // Scripts inserted via innerHTML do not run, so re-create them
    const runScripts = (container) => {
        container.querySelectorAll("script").forEach((oldScript) => {
            const script = document.createElement("script");
            [...oldScript.attributes].forEach((a) => script.setAttribute(a.name, a.value));
            script.textContent = oldScript.textContent;
            oldScript.replaceWith(script);
        });
    };

    // Highlight the active link in header/footer
    const updateActiveLinks = () => {
        const current = location.pathname.replace(/\/+$/, "") || "/";
        document.querySelectorAll("[data-link]").forEach((a) => {
            const href = new URL(a.href).pathname.replace(/\/+$/, "") || "/";
            a.classList.toggle("active", href === current);
        });
    };

    // The server maps the URL to /pages/... itself (incl. 404),
    // the router just requests the same URL with the XHR header.
    const loadPage = async (url) => {
        const id = ++requestId;
        const { pathname, search } = new URL(url, location.origin);

        try {
            const response = await fetch(pathname + search, {
                headers: { "X-Requested-With": "XMLHttpRequest" }
            });
            const html = await response.text();
            if (id !== requestId) return;

            content.innerHTML = html;   // 404 fragment is rendered too
            runScripts(content);
            updateActiveLinks();
            window.scrollTo(0, 0);
        } catch (err) {
            if (id !== requestId) return;
            content.innerHTML = "<h1>Server is unavailable</h1>";
        }
    };

    const navigateTo = (url) => {
        if (new URL(url, location.origin).href === location.href) return; // same URL
        history.pushState(null, "", url);
        loadPage(url);
    };

    // Clicks on links with [data-link]
    document.body.addEventListener("click", (e) => {
        if (e.defaultPrevented || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;

        const link = e.target.closest("[data-link]");
        if (!link || link.target === "_blank") return;
        if (new URL(link.href).origin !== location.origin) return; // external link

        e.preventDefault();
        navigateTo(link.href);
    });

    // Back / Forward
    window.addEventListener("popstate", () => loadPage(location.href));

    // First visit is rendered by index.php
    updateActiveLinks();
});