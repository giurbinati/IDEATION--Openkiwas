(function () {
    function el(tag, attrs = {}, children = []) {
        const node = document.createElement(tag);
        Object.entries(attrs).forEach(([k, v]) => {
            if (k === "class") node.className = v;
            else if (k === "html") node.innerHTML = v;
            else node.setAttribute(k, v);
        });
        children.forEach((c) =>
            node.appendChild(typeof c === "string" ? document.createTextNode(c) : c)
        );
        return node;
    }

    const root = document.getElementById("openkiwas-chat");
    if (!root) return;

    const endpoint = root.dataset.endpoint || "/rag/chat";

    const messagesBox = el("div", { class: "ok-messages" });
    const textarea = el("textarea", {
        class: "ok-textarea",
        rows: "1",
        placeholder: "Type your message...",
    });
    const button = el("button", { class: "ok-send", type: "button" }, ["Send"]);

    const shell = el("div", { class: "ok-shell" }, [
        messagesBox,
        el("div", { class: "ok-inputbar" }, [textarea, button]),
    ]);

    root.appendChild(shell);

    const footer = el(
        "div",
        { class: "ok-footer" },
        [
            el(
                "a",
                {
                    href: "https://huggingface.co/meta-llama/Meta-Llama-3-8B-Instruct/blob/main/LICENSE",
                    target: "_blank",
                    rel: "noopener noreferrer",
                    class: "ok-footer-link"
                },
                ["Built with Meta Llama 3"]
            )
        ]
    );

    root.appendChild(footer);


    let history = [];
    let loading = false;

    function renderText(text) {
        // Se hai già la libreria marked, puoi trasformare in markdown:
        if (window.marked) return window.marked.parse(text || "");
        // fallback semplice (senza markdown)
        return (text || "").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/\n/g, "<br>");
    }

    function addMessage(role, content, typing = false) {
        const msg = el("div", { class: `ok-msg ok-${role}` });

        // contenuto
        const body = el("div", {
            class: "ok-msg-body",
            html: typing
                ? `<span class="ok-typing"><span class="dot"></span><span class="dot"></span><span class="dot"></span></span>`
                : renderText(content),
        });

        msg.appendChild(body);

        // azioni SOLO per assistant e SOLO quando non è typing
        if (role === "assistant" && !typing) {
            msg.dataset.md = content || "";

            const actions = el("div", { class: "ok-actions" });

            const copyBtn = el("button", { class: "ok-action ok-copy", type: "button" }, ["Copy"]);
            const dlBtn = el("button", { class: "ok-action ok-download", type: "button" }, ["Download"]);

            copyBtn.addEventListener("click", async () => {
                const md = msg.dataset.md || "";
                try {
                    await navigator.clipboard.writeText(md);
                    copyBtn.textContent = "Copied!";
                    setTimeout(() => (copyBtn.textContent = "Copy"), 1000);
                } catch (e) {
                    alert("Impossibile copiare (permessi browser).");
                }
            });

            dlBtn.addEventListener("click", () => {
                const md = msg.dataset.md || "";
                const blob = new Blob([md], { type: "text/plain;charset=utf-8" });
                const url = URL.createObjectURL(blob);

                const a = document.createElement("a");
                a.href = url;

                const ts = new Date().toISOString().slice(0, 19).replace(/[:T]/g, "-");
                a.download = `openkiwas-answer-${ts}.txt`;

                document.body.appendChild(a);
                a.click();
                a.remove();
                URL.revokeObjectURL(url);
            });


            actions.appendChild(copyBtn);
            actions.appendChild(dlBtn);
            msg.appendChild(actions);
        }

        messagesBox.appendChild(msg);
        messagesBox.scrollTop = messagesBox.scrollHeight;
        return msg;
    }


    async function send() {
        const question = textarea.value.trim();
        if (!question || loading) return;

        loading = true;
        button.disabled = true;

        addMessage("user", question);
        textarea.value = "";

        const typingEl = addMessage("assistant", "", true);

        try {
            const res = await fetch(endpoint, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ question, history }),
            });

            const text = await res.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch {
                throw new Error(text.slice(0, 200));
            }
            if (!res.ok) throw new Error(data.error || "Errore");

            // rimuovo il typing
            typingEl.remove();

            // aggiungo il messaggio assistant finale con bottoni
            addMessage("assistant", data.response || "(nessuna risposta)", false);

            history = [
                ...history,
                { role: "user", content: question },
                { role: "assistant", content: data.response || "" },
            ];
            messagesBox.scrollTop = messagesBox.scrollHeight;
        } catch (e) {
            typingEl.innerHTML = renderText(`❌ Error: ${e.message}`);
        } finally {
            loading = false;
            button.disabled = false;
            textarea.focus();
        }
    }

    button.addEventListener("click", send);
    textarea.addEventListener("keydown", (e) => {
        if (e.key === "Enter" && !e.shiftKey) {
            e.preventDefault();
            send();
        }
    });

    textarea.focus();
})();
