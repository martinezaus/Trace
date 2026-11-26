<header class="topbar">
  <div class="brand">CONFIGURACIONES<span> · </span><?= strtoupper(get_role()) ?></div>
  <nav>
    <a href="dashboard.php">Panel</a>
    <a href="negocio.php">Negocio</a>
    <a href="productividad.php">Productividad y costos</a>
    <a href="smtp.php">SMTP</a>
	<a href="remoto.php" target="_blank" rel="noopener noreferrer">Soporte remoto</a>
    <a href="parametros.php">Parametros</a>
    <?php if (get_role() === 'master'): ?>
      <a href="usage.php">Sistema</a>
    <?php endif; ?>
    <a href="logs.php">Logs</a>
    <a href="logout.php">Salir</a>
  </nav>
</header>
