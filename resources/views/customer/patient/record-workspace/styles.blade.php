@push('styles')
<style>
    .clinical-workspace { --clinical-accent: {{ themeColor(600) }}; --clinical-tint: {{ themeColor(50) }}; display: flex; flex-direction: column; gap: 12px; min-height: 400px; position: relative; }
    .clinical-patient-header { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; border: 1px solid {{ themeColor(200) }}; background: var(--clinical-tint); padding: 16px; border-radius: 14px; flex-shrink: 0; }
    .clinical-panels { display: grid; grid-template-columns: minmax(0,1fr) minmax(0,1fr); gap: 14px; flex: 1; min-height: 0; }
    [data-ratio="40"] .clinical-panels { grid-template-columns: minmax(0,4fr) minmax(0,6fr); }
    [data-ratio="60"] .clinical-panels { grid-template-columns: minmax(0,6fr) minmax(0,4fr); }
    .clinical-panel { display: flex; flex-direction: column; border: 1px solid #e5e7eb; border-radius: 14px; background: white; min-width: 0; min-height: 0; overflow: hidden; }
    .clinical-panel-scroll { min-height: 0; flex: 1; overflow-y: auto; overflow-x: hidden; overscroll-behavior-y: auto; scrollbar-width: thin; padding: 16px; }
    .clinical-record-tabs { display: flex; flex-wrap: wrap; gap: 6px; padding: 12px; border-bottom: 1px solid #e5e7eb; flex-shrink: 0; }
    .clinical-record-tabs button, .clinical-mobile-tabs button { border: 1px solid #e5e7eb; border-radius: 8px; padding: 8px 10px; color: #4b5563; font-size: 12px; font-weight: 600; }
    .clinical-record-tabs [aria-selected="true"], .clinical-mobile-tabs [aria-pressed="true"] { color: white; background: var(--clinical-accent); border-color: var(--clinical-accent); }
    .clinical-mobile-tabs { display: none; }
    .clinical-workspace h2 { font-size: 1rem; }
    .clinical-workspace .grid { min-width: 0; }
    .clinical-record-panel .md\:grid-cols-3 { grid-template-columns: repeat(2,minmax(0,1fr)); }
    .clinical-record-panel .md\:grid-cols-2, .clinical-record-panel .lg\:grid-cols-3 { grid-template-columns: minmax(0,1fr); }
    .clinical-attention-panel .md\:grid-cols-5 { grid-template-columns: repeat(2,minmax(0,1fr)); }
    .clinical-workspace .min-w-56, .clinical-workspace .min-w-48, .clinical-workspace .min-w-64 { min-width: min(100%, 12rem); }
    .clinical-workspace input, .clinical-workspace textarea, .clinical-workspace select { max-width: 100%; }
    .clinical-workspace p, .clinical-workspace dd { overflow-wrap: anywhere; }
    .clinical-workspace [id] { scroll-margin-top: 12px; }
    .clinical-workspace button:focus-visible, .clinical-workspace a:focus-visible { outline: 2px solid var(--clinical-accent); outline-offset: 2px; }
    .clinical-video { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; margin-bottom: 16px; }
    .clinical-video-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 8px; background: var(--clinical-tint); font-size: 12px; }
    .clinical-video-toolbar button { border: 1px solid #d1d5db; border-radius: 6px; padding: 6px 8px; background: white; }
    .clinical-patient-invite { display: flex; align-items: center; flex-wrap: wrap; gap: 12px; padding: 14px; background: linear-gradient(120deg,white,var(--clinical-tint)); border-bottom: 1px solid {{ themeColor(100) }}; }
    .clinical-patient-invite-heading { display: flex; align-items: center; gap: 10px; flex: 1 1 180px; min-width: 0; }
    .clinical-patient-invite-icon { display: grid; place-items: center; flex-shrink: 0; width: 38px; height: 38px; border-radius: 12px; color: var(--clinical-accent); background: {{ themeColor(100) }}; }
    .clinical-patient-invite-title { margin: 0; font-size: 13px; font-weight: 700; color: #1f2937; }
    .clinical-patient-invite-description { margin: 3px 0 0; font-size: 11px; color: #6b7280; }
    .clinical-copy-patient-link { display: inline-flex; align-items: center; justify-content: center; gap: 9px; min-height: 42px; padding: 10px 14px; border: 1px solid transparent; border-radius: 11px; background: var(--clinical-accent); color: white; font-size: 12px; font-weight: 600; box-shadow: 0 3px 8px #00000012; transition: background-color .2s,box-shadow .2s,transform .2s; }
    .clinical-copy-patient-link:hover:not(:disabled) { background: {{ themeColor(700) }}; box-shadow: 0 4px 12px #00000020; transform: translateY(-1px); }
    .clinical-copy-patient-link:active:not(:disabled) { transform: translateY(0); }
    .clinical-copy-patient-link:disabled { cursor: wait; opacity: .8; }
    .clinical-copy-patient-link[data-copied="true"] { background: #047857; }
    .clinical-copy-patient-link-trailing { opacity: .7; }
    .clinical-patient-invite-note { display: flex; align-items: flex-start; gap: 6px; flex-basis: 100%; margin: 0; color: #6b7280; font-size: 11px; line-height: 1.5; }
    .clinical-patient-invite-note i { margin-top: 2px; color: var(--clinical-accent); }
    .clinical-patient-invite-manual { width: 100%; font-size: 12px; color: #4b5563; }
    .clinical-patient-invite-manual input { width: 100%; margin-top: 6px; border: 1px solid {{ themeColor(200) }}; border-radius: 8px; padding: 8px; background: white; font-size: 12px; }
    .clinical-video[data-floating="true"] .clinical-patient-invite { gap: 10px; padding: 12px; }
    .clinical-video[data-floating="true"] .clinical-copy-patient-link { width: 100%; }
    @media(prefers-reduced-motion:reduce) { .clinical-copy-patient-link { transition: none; } .clinical-copy-patient-link:hover:not(:disabled) { transform: none; } }
    .clinical-video iframe { width: 100%; height: 480px; border: 0; display: block; }
    [data-video-mode="mini"] .clinical-video { width: min(320px,calc(100vw - 24px)); margin: 0; box-shadow: 0 10px 30px #0003; }
    [data-video-mode="mini"] .clinical-video iframe { height: 240px; }
    .clinical-video[data-floating="true"] { position: fixed !important; z-index: 60 !important; inset: auto !important; left: var(--video-x,12px) !important; top: var(--video-y,12px) !important; margin: 0 !important; max-height: calc(100dvh - 16px); overflow-y: auto; }
    .clinical-video[data-floating="true"] .clinical-video-toolbar { cursor: grab; touch-action: none; user-select: none; }
    .clinical-video[data-dragging="true"] .clinical-video-toolbar { cursor: grabbing; }
    .clinical-video[data-dragging="true"] iframe { pointer-events: none; }
    .clinical-video::backdrop { background: transparent; pointer-events: none; }
    [data-video-mode="expanded"] .clinical-video { position: absolute; z-index: 45; inset: 8px; margin: 0; display: flex; flex-direction: column; box-shadow: 0 10px 30px #0003; }
    [data-video-mode="expanded"] .clinical-video iframe { height: auto; flex: 1; min-height: 0; }
    @media(min-width:768px) { .clinical-workspace { height: var(--clinical-workspace-height,max(780px,calc(100dvh - 220px))); min-height: 780px; } }
    @media(max-width:767px) {
        .clinical-workspace { height: var(--clinical-workspace-height,max(660px,calc(100dvh - 160px))); min-height: 660px; }
        .clinical-panels, [data-ratio] .clinical-panels { display: flex; flex-direction: column; }
        .clinical-panel { flex: 1; }
        .clinical-mobile-tabs { display: flex; gap: 8px; flex-shrink: 0; }
        .clinical-ratio { display: none; }
        [data-mobile-panel="attention"] .clinical-record-panel { display: none; }
        [data-mobile-panel="record"] .clinical-attention-panel { visibility: hidden; position: absolute; inset: 0; width: 100%; height: 100%; pointer-events: none; }
        [data-mobile-panel="record"] .clinical-attention-panel .clinical-video { visibility: visible; pointer-events: auto; }
        [data-mobile-panel="record"][data-video-mode="normal"] .clinical-video { position: absolute; bottom: 12px; right: 12px; z-index: 45; width: min(300px,calc(100% - 24px)); }
        [data-mobile-panel="record"][data-video-mode="normal"] .clinical-video iframe { height: 240px; }
        [data-mobile-panel="record"][data-video-loaded="false"] .clinical-video { visibility: hidden; pointer-events: none; }
        .clinical-patient-header { padding: 12px; }
        .clinical-workspace textarea { font-size: 16px; }
        .clinical-record-panel .md\:grid-cols-3 { grid-template-columns: minmax(0,1fr); }
    }
</style>
@endpush
