<?php
session_start();

// Configuración de conexión a la base de datos
$db_host = "mysql-bibliotecadonbosco.alwaysdata.net";
$db_user = "bibliotecadonbosco_kevin";
$db_pass = "FerYH23!.LuAuThMo2007";
$db_name = "bibliotecadonbosco_bibliotecadonbosco";

$conexion = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conexion->connect_error) {
    die("❌ Error de conexión: " . $conexion->connect_error);
}
$conexion->set_charset("utf8mb4");

// 🔹 Manejo de acciones administrativas (Registrar, Editar, Eliminar)
$mensajeAdmin = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $accion = $_POST['accion'];

    if ($accion === 'registrar') {
        $stmt = $conexion->prepare("INSERT INTO Bibliogene (nombre, estanteria, codigo, genero, autor, stock) VALUES (?, ?, ?, ?, ?, ?)");
        $stockInt = (int)$_POST['stock'];
        $stmt->bind_param("sssssi", $_POST['nombre'], $_POST['estanteria'], $_POST['codigo'], $_POST['clasificacion'], $_POST['autor'], $stockInt);
        if ($stmt->execute()) {
            $mensajeAdmin = "<p style='color:green; text-align:center;'>✅ Libro registrado correctamente en Biblioteca General.</p>";
        } else {
            $mensajeAdmin = "<p style='color:red; text-align:center;'>❌ Error al registrar el libro: " . htmlspecialchars($stmt->error) . "</p>";
        }
        $stmt->close();
    }

    if ($accion === 'editar') {
        $codigo = $_POST['codigo'];
        $campos = [];
        $params = [];
        $types = "";

        if (!empty($_POST['nombre'])) { $campos[] = "nombre=?"; $params[] = $_POST['nombre']; $types .= "s"; }
        if (!empty($_POST['estanteria'])) { $campos[] = "estanteria=?"; $params[] = $_POST['estanteria']; $types .= "s"; }
        if (!empty($_POST['clasificacion'])) { $campos[] = "genero=?"; $params[] = $_POST['clasificacion']; $types .= "s"; }
        if (!empty($_POST['autor'])) { $campos[] = "autor=?"; $params[] = $_POST['autor']; $types .= "s"; }
        if (isset($_POST['stock']) && $_POST['stock'] !== '') { $campos[] = "stock=?"; $params[] = (int)$_POST['stock']; $types .= "i"; }

        if (count($campos) > 0) {
            $sql = "UPDATE Bibliogene SET " . implode(", ", $campos) . " WHERE codigo=?";
            $params[] = $codigo;
            $types .= "s";
            
            $stmt = $conexion->prepare($sql);
            $stmt->bind_param($types, ...$params);
            if ($stmt->execute()) {
                $mensajeAdmin = "<p style='color:green; text-align:center;'>✅ Libro actualizado correctamente.</p>";
            } else {
                $mensajeAdmin = "<p style='color:red; text-align:center;'>❌ Error al actualizar el libro.</p>";
            }
            $stmt->close();
        }
    }

    if ($accion === 'eliminar') {
        $codigo = $_POST['codigo'];
        $stmt = $conexion->prepare("DELETE FROM Bibliogene WHERE codigo=?");
        $stmt->bind_param("s", $codigo);
        if ($stmt->execute()) {
            $mensajeAdmin = "<p style='color:green; text-align:center;'>✅ Libro eliminado correctamente.</p>";
        } else {
            $mensajeAdmin = "<p style='color:red; text-align:center;'>❌ Error al eliminar el libro.</p>";
        }
        $stmt->close();
    }
}

// 🔹 Cargar stock actual
$stockActual = [];
$resStock = $conexion->query("SELECT codigo, stock_disponible FROM stock_actual");
if ($resStock && $resStock->num_rows > 0) {
    while ($fila = $resStock->fetch_assoc()) {
        $stockActual[$fila['codigo']] = (int)$fila['stock_disponible'];
    }
}

// 🔹 Cargar libros generales
$librosGeneral = [];
$resultado = $conexion->query("SELECT nombre, estanteria, codigo, genero, autor, stock FROM Bibliogene");
if ($resultado && $resultado->num_rows > 0) {
    while ($fila = $resultado->fetch_assoc()) {
        $librosGeneral[] = [
            "nombre"      => $fila["nombre"],
            "estanteria"  => $fila["estanteria"],
            "codigo"      => $fila["codigo"],
            "genero"      => $fila["genero"],
            "autor"       => $fila["autor"],
            "stock"       => (int)$fila["stock"]
        ];
    }
}

// 🔹 Cargar libros niños
$librosNinos = [];
$resultado1 = $conexion->query("SELECT nombre, estanteria, codigo, genero, autor, stock FROM Bibliotecaninos");
if ($resultado1 && $resultado1->num_rows > 0) {
    while ($fila = $resultado1->fetch_assoc()) {
        $librosNinos[] = [
            "nombre"      => $fila["nombre"],
            "estanteria"  => $fila["estanteria"],
            "codigo"      => $fila["codigo"],
            "genero"      => $fila["genero"],
            "autor"       => $fila["autor"],
            "stock"       => (int)$fila["stock"]
        ];
    }
}

// 🔹 Variables básicas
$seccion = $_POST["seccion"] ?? "general"; 
$generoSeleccionado = $_POST["genero"] ?? "";
$nombreBuscar = trim($_POST["nombreBuscar"] ?? "");
$autorBuscar = trim($_POST["autorBuscar"] ?? "");
$codigoBuscar = trim($_POST["codigoBuscar"] ?? "");

$libros = ($seccion == "ninos") ? $librosNinos : $librosGeneral;

$librosFiltrados = ($generoSeleccionado == "" || $generoSeleccionado == "Todos") 
    ? $libros 
    : array_filter($libros, fn($libro) => $libro["genero"] == $generoSeleccionado);

$mostrarTabla = ($_SERVER["REQUEST_METHOD"] == "POST" && 
                ($generoSeleccionado != "" || isset($_POST["buscar"])) || 
                !empty($nombreBuscar) || !empty($autorBuscar));

$mensajeOperacion = "";

// ✅ DEVOLVER LIBRO
if (isset($_POST["devolver"])) {
    $idDevuelto = (int) $_POST["devolver"];

    $stmt = $conexion->prepare("SELECT codigo_libro, nombre_libro FROM prestamos WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $idDevuelto);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $res->num_rows > 0) {
        $libroDevuelto = $res->fetch_assoc();
        $codigoDevuelto = $libroDevuelto["codigo_libro"];

        if (isset($stockActual[$codigoDevuelto])) {
            $stockActual[$codigoDevuelto] += 1;
            $stmtUpd = $conexion->prepare("UPDATE stock_actual SET stock_disponible = ? WHERE codigo = ?");
            $stmtUpd->bind_param("is", $stockActual[$codigoDevuelto], $codigoDevuelto);
            $stmtUpd->execute();
            $stmtUpd->close();
        }

        $stmtDel = $conexion->prepare("DELETE FROM prestamos WHERE id = ?");
        $stmtDel->bind_param("i", $idDevuelto);
        $stmtDel->execute();
        $stmtDel->close();

        $mensajeOperacion = "<p style='color:green;'>🔁 Libro <strong>" . htmlspecialchars($libroDevuelto['nombre_libro']) . "</strong> devuelto correctamente. Stock actualizado.</p>";
    }
    $stmt->close();
}

// ✅ PRESTAR LIBRO
if (isset($_POST["prestar"])) {
    $codigoAlumno = trim($_POST["codigoAlumno"] ?? "");
    $nombreAlumno = trim($_POST["nombreAlumno"] ?? "");

    if (empty($codigoAlumno) || empty($codigoBuscar) || empty($nombreAlumno)) {
        $mensajeOperacion = "<p style='color:red;'>⚠️ Rellena todos los campos para realizar el préstamo.</p>";
    } else {
        if (isset($stockActual[$codigoBuscar])) {
            if ($stockActual[$codigoBuscar] > 0) {
                $stockActual[$codigoBuscar] -= 1;
                
                $stmtUpd = $conexion->prepare("UPDATE stock_actual SET stock_disponible = ? WHERE codigo = ?");
                $stmtUpd->bind_param("is", $stockActual[$codigoBuscar], $codigoBuscar);
                $stmtUpd->execute();
                $stmtUpd->close();

                date_default_timezone_set('America/Guatemala');
                $fechaHora = date("Y-m-d H:i:s");

                foreach (array_merge($librosGeneral, $librosNinos) as $libro) {
                    if ($libro["codigo"] === $codigoBuscar) {
                        $stmtIns = $conexion->prepare("
                            INSERT INTO prestamos 
                            (codigo_libro, nombre_libro, autor, codigo_alumno, nombre_alumno, fecha_prestamo)
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        $stmtIns->bind_param(
                            "ssssss",
                            $libro["codigo"],
                            $libro["nombre"],
                            $libro["autor"],
                            $codigoAlumno,
                            $nombreAlumno,
                            $fechaHora
                        );
                        $stmtIns->execute();
                        $stmtIns->close();

                        $mensajeOperacion = "<p style='color:green;'>✅ Prestaste el libro <strong>" . htmlspecialchars($libro["nombre"]) . "</strong>. Stock actual: {$stockActual[$codigoBuscar]}</p>";
                        break;
                    }
                }
            } else {
                $mensajeOperacion = "<p style='color:red;'>⚠ No hay stock disponible para el libro con código <strong>" . htmlspecialchars($codigoBuscar) . "</strong>.</p>";
            }
        } else {
            $mensajeOperacion = "<p style='color:red;'>❌ Código de libro no encontrado.</p>";
        }
        $mostrarTabla = true;
    }
}

// 🔍 BUSCAR LIBROS
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["buscar"])) {
    $librosFiltrados = [];
    $umbral = 3;

    foreach ($libros as $libro) {
        $matchNombre = !empty($nombreBuscar) && (levenshtein(strtoupper($nombreBuscar), strtoupper($libro["nombre"])) <= $umbral || stripos($libro["nombre"], $nombreBuscar) !== false);
        $matchAutor  = !empty($autorBuscar) && (levenshtein(strtoupper($autorBuscar), strtoupper($libro["autor"])) <= $umbral || stripos($libro["autor"], $autorBuscar) !== false);
        $matchCodigo = !empty($codigoBuscar) && stripos($libro["codigo"], $codigoBuscar) !== false;

        if ($matchNombre || $matchAutor || $matchCodigo) {
            $librosFiltrados[] = $libro;
        }
    }
    $mostrarTabla = true;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Biblioteca Don Bosco</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0; padding: 0;
            background: url("folder/fondo_colegio.jpg") no-repeat center center fixed;
            background-size: cover;
        }
        body::before {
            content: ""; position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.5); z-index: -1;
        }
        .navbar {
            width: 100%; background: #2B7FFF; padding: 12px 0; text-align: center;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
        }
        .navbar a {
            color: white; margin: 0 15px; font-weight: bold; text-decoration: none; transition: 0.3s;
        }
        .navbar a:hover { text-decoration: underline; }
        .container {
            width: 90%; max-width: 900px; background: #F6F1E9; padding: 30px; margin: 40px auto;
            border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); display: none;
        }
        .active { display: block; }
        .logo { width: 90px; display: block; margin: 0 auto 15px auto; border-radius: 50%; }
        h2 { text-align: center; color: #2B7FFF; margin-bottom: 20px; }
        h3 { text-align: center; color: #444; margin-top: 10px; }
        form { text-align: center; margin: 15px 0; }
        label { font-weight: bold; margin-right: 10px; }
        input[type="text"], input[type="number"], select {
            padding: 10px; width: 250px; margin: 8px 0; border: 1px solid #ccc; border-radius: 6px;
        }
        button {
            padding: 10px 25px; background: #2B7FFF; color: white; border: none; border-radius: 6px;
            cursor: pointer; font-size: 15px; transition: 0.3s;
        }
        button:hover { background: #1a5edc; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: center; }
        th { background: #2B7FFF; color: white; }
        tr:nth-child(even) { background: #f9f9f9; }
        tr:hover { background: #eaf1ff; }
        .creditos-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 15px; margin-top: 20px;
        }
        .creditos-grid div {
            background: #f1f7ff; padding: 12px; border-radius: 8px;
            text-align: center; font-weight: bold; color: #2B7FFF; box-shadow: 0 3px 6px rgba(0,0,0,0.1);
        }
        .admin-section {
            background: #ffffff; padding: 25px; border-radius: 12px; margin: 30px auto; width: 90%; max-width: 900px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        }
    </style>
</head>
<body>

    <!-- Barra de navegación -->
    <div class="navbar">
        <a href="#" onclick="mostrarTab('biblioteca')">Biblioteca</a>
        <a href="#" onclick="mostrarTab('info')">Información</a>
        <a href="#" onclick="mostrarTab('creditos')">Créditos</a>
        <a href="#" onclick="pedirContrasena('Libpres')">Libros Prestados</a>
        <a href="#" onclick="pedirContrasena('RegLibpres')">Regresar Libros Prestados</a>
    </div>

    <?= $mensajeOperacion ?>

    <!-- Pestaña Biblioteca -->
    <div class="container active" id="biblioteca">
        <h2>Biblioteca Colegio Salesiano Don Bosco</h2>
        <img src="folder/logo-colegio.png" alt="Logo Colegio" class="logo">

        <!-- Selección de biblioteca -->
        <form method="post">
            <label>Seleccionar biblioteca: </label>
            <select name="seccion" onchange="this.form.submit()">
                <option value="general" <?= $seccion=="general"?"selected":"" ?>>Biblioteca General</option>
                <option value="ninos" <?= $seccion=="ninos"?"selected":"" ?>>Área de Niños</option>
            </select>
        </form>

        <!-- Filtro de género -->
        <form method="post">
            <input type="hidden" name="seccion" value="<?= htmlspecialchars($seccion) ?>">
            <label>Filtrar por clasificación: </label>
            <select name="genero" onchange="this.form.submit()">
                <option value="" <?= $generoSeleccionado==""?"selected":"" ?>>-- Selecciona --</option>
                <option value="Todos" <?= $generoSeleccionado=="Todos"?"selected":"" ?>>Todos</option>
                <?php
                $generos = array_unique(array_column($libros, "genero"));
                foreach ($generos as $g) {
                    echo "<option value='" . htmlspecialchars($g) . "' " . ($generoSeleccionado == $g ? "selected" : "") . ">" . htmlspecialchars($g) . "</option>";
                }
                ?>
            </select>
        </form>

        <!-- Buscador -->
        <form method="post">
            <input type="hidden" name="seccion" value="<?= htmlspecialchars($seccion) ?>">
            <label>Nombre del libro: </label>
            <input type="text" name="nombreBuscar" placeholder="Escribe el nombre" value="<?= htmlspecialchars($nombreBuscar) ?>"><br>
            <label>Autor: </label>
            <input type="text" name="autorBuscar" placeholder="Escribe el autor" value="<?= htmlspecialchars($autorBuscar) ?>"><br>
            <label>Código del libro: </label>
            <input type="text" name="codigoBuscar" placeholder="Escribe el código" value="<?= htmlspecialchars($codigoBuscar) ?>"><br>
            <button type="submit" name="buscar">Buscar</button>
        </form>

        <!-- Tabla de Resultados -->
        <?php if ($mostrarTabla): ?>
        <table>
            <thead>
                <tr>
                    <th>Nombre del libro</th>
                    <th>Estantería</th>
                    <th>Código</th>
                    <th>Clasificación</th>
                    <th>Autor</th>
                    <th>Stock</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($librosFiltrados) > 0): ?>
                    <?php foreach ($librosFiltrados as $libro): ?>
                    <tr>
                        <td><?= htmlspecialchars($libro["nombre"]) ?></td>
                        <td><?= htmlspecialchars($libro["estanteria"]) ?></td>
                        <td><?= htmlspecialchars($libro["codigo"]) ?></td>
                        <td><?= htmlspecialchars($libro["genero"]) ?></td>
                        <td><?= htmlspecialchars($libro["autor"]) ?></td>
                        <td><?= htmlspecialchars($libro["stock"]) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6">No se encontraron libros con los criterios seleccionados.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- Pestaña Información -->
    <div class="container" id="info">
        <h2>Información del Colegio</h2>
        <p style="color:#333; text-align:center;">
            La biblioteca del Colegio Salesiano Don Bosco ofrece una amplia variedad de libros en distintas categorías, disponibles para todos los estudiantes.
        </p>
        <ul style="max-width: 400px; margin: 0 auto; text-align: left;">
            <li><strong>Dirección:</strong> 26 Calle 2-46, zona 1, Ciudad de Guatemala</li>
            <li><strong>Teléfono:</strong> +502 2246-5100</li>
            <li><strong>Fax:</strong> +502 2246-5109</li>
        </ul>
    </div>

    <!-- Pestaña Créditos -->
    <div class="container" id="creditos">
        <h2>Créditos</h2>
        <p style="color:#2B7FFF; text-align:center; font-weight:bold;">Proyecto desarrollado por:</p>
        <div class="creditos-grid">
            <div>Luis Andrés Tagüite Cifuentes</div>
            <div>Santiago López Leonardo</div>
            <div>José Miguel Argueta Alvarado</div>
            <div>Kevin Andrés Ventura Rustrian</div>
            <div>Gioacchino Yanes</div>
            <div>Juan Pablo Chamalé Meda</div>
            <div>André Gabriel Morales Chinchilla</div>
            <div>Raúl Eduardo Mejía Castillo</div>
            <div>Sebastian Díaz Castro</div>
        </div>
        <p style="text-align:center; color:#2B7FFF; font-weight:bold; margin-top:20px;">
            Promoción: LXXVI <br> Año: 2025
        </p>
    </div>

    <!-- Pestaña Prestar Libros -->
    <div class="container" id="Libpres">
        <h2>Solicitar Préstamo de Libro</h2>

        
        <form method="post">
            <label>Código del libro: </label>
            <input type="text" name="codigoBuscar" placeholder="Código del libro" required><br>
            <label>Código del alumno: </label>
            <input type="text" name="codigoAlumno" placeholder="Código del alumno" required><br>
            <label>Nombre del alumno: </label>
            <input type="text" name="nombreAlumno" placeholder="Nombre del alumno" required><br><br>
            <button type="submit" name="prestar">Prestar libro</button>
        </form>
    </div>

    <!-- Pestaña Regresar Libros -->
    <div class="container" id="RegLibpres">
        <h2>📚 Regresar Libros Prestados</h2>
        <?php
        $prestamos = [];
        $res = $conexion->query("SELECT * FROM prestamos ORDER BY fecha_prestamo DESC");
        if ($res && $res->num_rows > 0) {
            while ($fila = $res->fetch_assoc()) {
                $prestamos[] = $fila;
            }
        }
        ?>

        <?php if (count($prestamos) > 0): ?>
            <form method="post">
                <table>
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Código</th>
                            <th>Autor</th>
                            <th>Código Alumno</th>
                            <th>Nombre Alumno</th>
                            <th>Fecha Préstamo</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($prestamos as $p): ?>
                            <tr>
                                <td><?= htmlspecialchars($p["nombre_libro"]) ?></td>
                                <td><?= htmlspecialchars($p["codigo_libro"]) ?></td>
                                <td><?= htmlspecialchars($p["autor"]) ?></td>
                                <td><?= htmlspecialchars($p["codigo_alumno"]) ?></td>
                                <td><?= htmlspecialchars($p["nombre_alumno"]) ?></td>
                                <td><?= htmlspecialchars($p["fecha_prestamo"]) ?></td>
                                <td>
                                    <button type="submit" name="devolver" value="<?= $p["id"] ?>">Devolver</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </form>
        <?php else: ?>
            <p style="text-align:center;">No hay libros prestados actualmente.</p>
        <?php endif; ?>
    </div>

    <!-- SECCIÓN ADMINISTRATIVA (REGISTRAR / EDITAR / ELIMINAR) -->
    <div class="admin-section">
        <h2 style="color:#0056b3;">Gestión de Libros (Administración)</h2>
        <?= $mensajeAdmin ?>
        
        <div style="display:flex; justify-content:center; gap:20px; flex-wrap:wrap;">
            <!-- Registrar libro -->
            <div style="background:#f8f9fa; padding:20px; border-radius:12px; width:260px; box-shadow:0 2px 5px rgba(0,0,0,0.1);">
                <h3>Registrar libro</h3>
                <form method="POST">
                    <input type="hidden" name="accion" value="registrar">
                    <label>Nombre:</label><br>
                    <input type="text" name="nombre" required><br>
                    <label>Estantería:</label><br>
                    <input type="text" name="estanteria" required><br>
                    <label>Código:</label><br>
                    <input type="text" name="codigo" required><br>
                    <label>Clasificación:</label><br>
                    <input type="text" name="clasificacion" required><br>
                    <label>Autor:</label><br>
                    <input type="text" name="autor" required><br>
                    <label>Stock:</label><br>
                    <input type="number" name="stock" min="0" required><br><br>
                    <button type="submit">Registrar</button>
                </form>
            </div>

            <!-- Editar libro -->
            <div style="background:#f8f9fa; padding:20px; border-radius:12px; width:260px; box-shadow:0 2px 5px rgba(0,0,0,0.1);">
                <h3>Editar libro</h3>
                <form method="POST">
                    <input type="hidden" name="accion" value="editar">
                    <label>Código objetivo:</label><br>
                    <input type="text" name="codigo" required><br>
                    <label>Nuevo nombre:</label><br>
                    <input type="text" name="nombre"><br>
                    <label>Nueva estantería:</label><br>
                    <input type="text" name="estanteria"><br>
                    <label>Nueva clasificación:</label><br>
                    <input type="text" name="clasificacion"><br>
                    <label>Nuevo autor:</label><br>
                    <input type="text" name="autor"><br>
                    <label>Nuevo stock:</label><br>
                    <input type="number" name="stock" min="0"><br><br>
                    <button type="submit">Actualizar</button>
                </form>
            </div>

            <!-- Eliminar libro -->
            <div style="background:#f8f9fa; padding:20px; border-radius:12px; width:260px; box-shadow:0 2px 5px rgba(0,0,0,0.1);">
                <h3>Eliminar libro</h3>
                <form method="POST" onsubmit="return confirm('¿Estás seguro de eliminar este libro?');">
                    <input type="hidden" name="accion" value="eliminar">
                    <label>Código del libro:</label><br>
                    <input type="text" name="codigo" required><br><br>
                    <button type="submit" style="background:red; color:white;">Eliminar</button>
                </form>
            </div>
        </div>
    </div>

    <!-- JavaScript para el control de pestañas y contraseñas -->
    <script>
        function mostrarTab(tabId) {
            const secciones = ['biblioteca', 'info', 'creditos', 'Libpres', 'RegLibpres'];
            secciones.forEach(id => {
                const elem = document.getElementById(id);
                if (elem) elem.style.display = 'none';
            });

            const activa = document.getElementById(tabId);
            if (activa) activa.style.display = 'block';

            localStorage.setItem('pestañaActiva', tabId);
        }

        function pedirContrasena(tabId) {
            const contraseñaCorrecta = "donbosco123";
            const intento = prompt("Ingresa la contraseña para acceder:");

            if (intento === contraseñaCorrecta) {
                mostrarTab(tabId);
            } else if (intento !== null) {
                alert("Contraseña incorrecta. Intenta nuevamente.");
            }
        }

        window.onload = function() {
            const pestaña = localStorage.getItem('pestañaActiva') || 'biblioteca';
            mostrarTab(pestaña);
        };
    </script>
</body>
</html>
<?php $conexion->close(); ?>
