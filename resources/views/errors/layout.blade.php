<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') — {{ setting('institute_name', 'Pramana Institute') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            background: radial-gradient(circle at 50% 20%, #1e1b18 0%, #0e0d0c 100%);
            color: #e5e0db;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .container {
            max-width: 32rem;
            text-align: center;
            background: rgba(30, 27, 24, 0.7);
            border: 1px solid rgba(245, 158, 11, 0.2);
            border-radius: 1.5rem;
            padding: 3rem 2rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7), 0 0 40px rgba(217, 119, 6, 0.1);
            backdrop-filter: blur(12px);
        }
        .badge {
            display: inline-block;
            font-size: 0.875rem;
            font-weight: 600;
            color: #f59e0b;
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.3);
            border-radius: 9999px;
            padding: 0.25rem 1rem;
            margin-bottom: 1.5rem;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }
        .code {
            font-size: 5rem;
            font-weight: 800;
            line-height: 1;
            background: linear-gradient(135deg, #fbbf24 0%, #d97706 50%, #b45309 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1rem;
        }
        .title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #fdfaf7;
            margin-bottom: 0.75rem;
        }
        .message {
            font-size: 1rem;
            color: #a8a29e;
            line-height: 1.6;
            margin-bottom: 2rem;
        }
        .button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
            color: #ffffff;
            font-weight: 600;
            font-size: 0.95rem;
            text-decoration: none;
            padding: 0.75rem 1.75rem;
            border-radius: 0.75rem;
            transition: all 0.2s ease;
            box-shadow: 0 10px 15px -3px rgba(180, 83, 9, 0.3);
        }
        .button:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 20px -3px rgba(180, 83, 9, 0.4);
        }
        .footer {
            margin-top: 2rem;
            font-size: 0.8rem;
            color: #78716c;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="badge">{{ setting('institute_name', 'Pramana Institute') }}</div>
        <div class="code">@yield('code')</div>
        <h1 class="title">@yield('heading')</h1>
        <p class="message">@yield('message')</p>
        <a href="/" class="button">Return to Sanctuary</a>
        <div class="footer">
            Need guidance? Reach out at {{ setting('contact_email', 'contact@pramana-institute.org') }}
        </div>
    </div>
</body>
</html>
