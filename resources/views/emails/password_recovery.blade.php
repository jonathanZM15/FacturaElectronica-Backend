<!doctype html>
<html>
  <head>
    <meta charset="utf-8">
    <title>Recuperación de contraseña</title>
  </head>
  <body>
    <p>Hola {{ $user->name ?? 'usuario' }},</p>
    <p>Hemos recibido una solicitud para restablecer la contraseña de tu cuenta.</p>
    <p>Puedes cambiar tu contraseña haciendo clic en el siguiente enlace:</p>
    <p><a href="{{ $url }}">Restablecer contraseña</a></p>
    <p>Si no solicitaste este cambio, ignora este correo.</p>
    <p>Saludos,<br />{{ config('app.name') }}</p>
  </body>
</html>
