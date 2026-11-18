<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'VITA') }}</title>
    <style>
        * { box-sizing: border-box; }
        html,body { margin:0; height:100%; overflow:hidden; font-family:system-ui,sans-serif; }
        #vita-module-frame { width:100%; height:100dvh; display:block; border:0; }
        #vita-persistent-call { position:fixed; z-index:100; left:var(--call-x,16px); top:var(--call-y,16px); width:min(var(--call-width,380px),calc(100vw - 24px)); height:min(var(--call-height,530px),calc(100dvh - 24px)); border:1px solid {{ themeColor(200) }}; border-radius:16px; background:white; box-shadow:0 18px 55px #0004; overflow:hidden; display:flex; flex-direction:column; }
        #vita-persistent-call[hidden] { display:none; }
        #vita-call-toolbar { display:flex; align-items:center; justify-content:space-between; gap:8px; padding:10px 12px; background:{{ themeColor(600) }}; color:white; cursor:grab; touch-action:none; user-select:none; }
        #vita-call-handle { flex:1; min-width:0; }
        #vita-call-toolbar strong { font-size:13px; display:block; }
        #vita-call-patient { font-size:11px; opacity:.85; display:block; max-width:100%; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .vita-call-actions { display:flex; gap:4px; }
        .vita-call-actions button { border:1px solid #ffffff50; background:#ffffff18; color:white; border-radius:7px; padding:5px 8px; cursor:pointer; font-size:16px; }
        .vita-call-actions button:focus-visible,#vita-call-handle:focus-visible { outline:2px solid white; outline-offset:2px; }
        #vita-call-frame { border:0; width:100%; flex:1; min-height:0; }
        #vita-call-return { border:0; border-bottom:1px solid #e5e7eb; background:{{ themeColor(50) }}; color:{{ themeColor(700) }}; padding:8px; font-weight:600; cursor:pointer; }
        #vita-persistent-call[data-minimized="true"] { height:auto; }
        #vita-persistent-call[data-minimized="true"] #vita-call-frame,#vita-persistent-call[data-minimized="true"] #vita-call-return { display:none; }
        #vita-persistent-call[data-expanded="true"]:not([data-minimized="true"]) { width:min(900px,calc(100vw - 24px)); height: min(760px,calc(100dvh - 24px)); }
        #vita-persistent-call[data-dragging="true"] #vita-call-frame,#vita-persistent-call[data-resizing="true"] #vita-call-frame { pointer-events:none; }
        .vita-call-resize { position:absolute; z-index:3; width:22px; height:22px; padding:0; border:0; background:transparent; touch-action:none; cursor:nwse-resize; }
        #vita-call-resize-se { bottom:0; right:0; background:linear-gradient(135deg,transparent 45%,{{ themeColor(600) }} 46%,{{ themeColor(600) }} 54%,transparent 55%,transparent 65%,{{ themeColor(600) }} 66%,{{ themeColor(600) }} 74%,transparent 75%); }
        #vita-call-resize-nw { top:0; left:0; }
        .vita-call-resize:focus-visible { outline:2px solid {{ themeColor(600) }}; outline-offset:-3px; }
        #vita-persistent-call[data-minimized="true"] .vita-call-resize { display:none; }
        #vita-shell-notice { position:fixed; z-index:110; top:16px; left:50%; transform:translateX(-50%); max-width:calc(100vw - 32px); padding:12px 18px; background:#1f2937; color:white; border-radius:12px; box-shadow:0 4px 16px #0003; font-size:14px; }
        #vita-shell-notice[hidden] { display:none; }
    </style>
@include('components.favicon')
</head>
<body>
    <iframe id="vita-module-frame" title="M&#243;dulos de Vita" allow="camera; microphone; autoplay; fullscreen" src="{{ request()->fullUrlWithQuery(['_vita_frame' => 1]) }}"></iframe>
    <section id="vita-persistent-call" hidden aria-label="Videollamada activa">
        <header id="vita-call-toolbar">
            <div id="vita-call-handle" tabindex="0" role="button" aria-label="Arrastrar videollamada; usa las flechas para moverla">
                <strong>Teleconsulta <span id="vita-call-timer" style="font-size:11px;font-weight:400"></span></strong><span id="vita-call-patient"></span>
            </div>
            <div class="vita-call-actions">
                <button id="vita-call-minimize" type="button" aria-label="Minimizar videollamada" title="Minimizar">&#8722;</button>
                <button id="vita-call-expand" type="button" aria-label="Ampliar videollamada" title="Ampliar">&#9974;</button>
                <button id="vita-call-close" type="button" aria-label="Cerrar videollamada" title="Cerrar">&#215;</button>
            </div>
        </header>
        <button id="vita-call-return" type="button">Volver a la atenci&#243;n</button>
        <iframe id="vita-call-frame" title="Sala de teleconsulta" allow="camera; microphone; autoplay; fullscreen"></iframe>
        <button id="vita-call-resize-se" class="vita-call-resize" type="button" aria-label="Cambiar tama?o de la videollamada; arrastra o usa las flechas" title="Arrastra para cambiar el tama?o"></button>
        <button id="vita-call-resize-nw" class="vita-call-resize" type="button" aria-label="Cambiar tama?o desde la esquina superior izquierda" title="Arrastra para cambiar el tama?o"></button>
    </section>
    <div id="vita-shell-notice" hidden role="status" aria-live="polite"></div>
    <script src="{{ asset('js/vita-shell.js') }}?v={{ filemtime(public_path('js/vita-shell.js')) }}"></script>
</body>
</html>
