<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Clínica no disponible</title>
    <style>
        * { box-sizing: border-box; }
        body {
            min-height: 100vh;
            margin: 0;
            display: grid;
            place-items: center;
            padding: 24px;
            background: #f3f4f6;
            color: #1f2937;
            font-family: Arial, sans-serif;
        }
        main {
            width: min(100%, 520px);
            padding: 40px 32px;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            background: #fff;
            text-align: center;
            box-shadow: 0 10px 30px rgba(15, 23, 42, .08);
        }
        .icon {
            width: 56px;
            height: 56px;
            margin: 0 auto 20px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            background: #fff7ed;
            color: #c2410c;
            font-size: 28px;
            font-weight: 700;
        }
        h1 { margin: 0 0 12px; font-size: 24px; }
        p { margin: 0; color: #6b7280; line-height: 1.6; }
        .clinic { margin-top: 10px; color: #374151; font-weight: 600; }
    </style>
</head>
<body>
    <main>
        <div class="icon" aria-hidden="true">!</div>
        <h1>Clínica no disponible</h1>
        <p>Esta clínica no está disponible actualmente. Si necesitas más información, contacta directamente con la clínica.</p>
        <p class="clinic">{{ $clinicName }}</p>
    </main>
</body>
</html>
