<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Importa file XML-like</title>
</head>
<body>
  <h1>Carica file XML-like</h1>
  <form action="save_xml.php" method="post" enctype="multipart/form-data">
    <input type="file" name="xmlfile" accept=".xml,.txt" required>
    <button type="submit">Carica e processa</button>
  </form>
</body>
</html>
