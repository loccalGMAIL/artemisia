<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Artemisia') }}</title>
        <style>
            :root {
                color-scheme: light dark;
                --bg: #0f0f10;
                --card: #18181b;
                --border: #27272a;
                --text: #f4f4f5;
                --muted: #a1a1aa;
                --accent: #f59e0b;
                --accent-text: #1c1917;
            }

            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                background: var(--bg);
                color: var(--text);
                font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
                padding: 1.5rem;
            }

            .card {
                width: 100%;
                max-width: 30rem;
                background: var(--card);
                border: 1px solid var(--border);
                border-radius: 1rem;
                padding: 2.5rem 2rem;
                text-align: center;
            }

            h1 {
                margin: 0 0 0.5rem;
                font-size: 1.75rem;
                font-weight: 700;
            }

            p.subtitle {
                margin: 0 0 2rem;
                color: var(--muted);
                font-size: 0.95rem;
            }

            .links {
                display: flex;
                flex-direction: column;
                gap: 0.75rem;
            }

            .btn {
                display: block;
                padding: 0.85rem 1.25rem;
                border-radius: 0.5rem;
                text-decoration: none;
                font-weight: 600;
                font-size: 0.95rem;
                transition: opacity 0.15s ease;
            }

            .btn:hover {
                opacity: 0.85;
            }

            .btn-primary {
                background: var(--accent);
                color: var(--accent-text);
            }

            .btn-secondary {
                background: transparent;
                color: var(--text);
                border: 1px solid var(--border);
            }

            footer {
                margin-top: 2rem;
                color: var(--muted);
                font-size: 0.8rem;
            }
        </style>
    </head>
    <body>
        <div class="card">
            <h1>Artemisia</h1>
            <p class="subtitle">Agencia de diseño y redes sociales</p>

            <div class="links">
                <a class="btn btn-primary" href="{{ $adminUrl }}">Acceso staff</a>
                <a class="btn btn-secondary" href="{{ $clienteUrl }}">Acceso clientes</a>
            </div>
        </div>
    </body>
</html>
