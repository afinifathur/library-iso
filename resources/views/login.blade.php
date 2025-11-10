<!DOCTYPE html>
<html>
<head>
    <title>Login - Document and Control</title>
    <style>
        body { font-family: Arial; background:#f3f5f7; padding-top:80px; }
        .box { width:320px; margin:auto; background:white; padding:20px; border-radius:8px; border:1px solid #ddd; }
        input { width:100%; padding:10px; margin-bottom:12px; border:1px solid #ccc; border-radius:6px; }
        button { width:100%; padding:10px; background:#1d4ed8; border:none; color:white; font-weight:bold; border-radius:6px; }
        h2 { margin:0 0 16px 0; text-align:center; }
        .err { color:red; margin-bottom:10px; }
    </style>
</head>
<body>

<div class="box">
    <h2>Login</h2>

    @if($errors->any())
        <div class="err">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="/login">
        @csrf
        <input type="text" name="email" placeholder="Email" required>
        <input type="password" name="password" placeholder="Password" required>

        <button>Login</button>
    </form>
</div>

</body>
</html>
