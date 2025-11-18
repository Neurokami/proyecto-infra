# Marketplace · Panel de Vendedores

Aplicación simple para la **gestión de vendedores** de un marketplace:

- Login por documento.
- Registro de nuevos vendedores.
- Publicación de productos.
- Listado de productos del vendedor.
- Listado de ventas asociadas a los productos del vendedor.

Frontend estático (HTML/CSS/JS) servido por Nginx y backend PHP (API REST sencilla) conectado a MariaDB.


## Estructura del proyecto

- [`index.html`](index.html:1) — SPA sencilla con:
  - Pantalla de **login** por documento.
  - Pantalla de **registro** de vendedor.
  - **Dashboard** con:
    - Formulario para publicar productos.
    - Tabla de productos.
    - Tabla de ventas.
- [`assets/css/styles.css`](assets/css/styles.css:1) — Estilos modernos responsivos.
- [`assets/js/app.js`](assets/js/app.js:1) — Lógica de frontend:
  - Manejo de vistas (login / registro / dashboard).
  - Llamadas `fetch` a los endpoints REST en `/api`.
  - Manejo de estado de sesión del vendedor en `sessionStorage`.
- Configuración de BD:
  - [`config/config.php`](config/config.php:1) — Capa de conexión a MariaDB con PDO.
  - [`api/bootstrap.php`](api/bootstrap.php:1) — Bootstrap API: CORS, helpers JSON, helpers para request/validación, obtención de PDO.
- Endpoints de API:
  - [`api/auth/login.php`](api/auth/login.php:1) — Login de vendedor por documento.
  - [`api/auth/register.php`](api/auth/register.php:1) — Registro de nuevo vendedor.
  - [`api/productos.php`](api/productos.php:1) — Listar y crear productos para un vendedor.
  - [`api/ventas.php`](api/ventas.php:1) — Listar ventas asociadas a los productos de un vendedor.


## Esquema de base de datos

Base de datos: **`marketplace`**

Usa las tablas que definiste:

```sql
CREATE DATABASE IF NOT EXISTS marketplace;
USE marketplace;

-- ==========================================
-- TABLA: vendedores (publican productos)
-- ==========================================
CREATE TABLE vendedores (
    id_vendedor INT AUTO_INCREMENT PRIMARY KEY,
    documento VARCHAR(45) NOT NULL UNIQUE,
    nombre VARCHAR(45) NOT NULL,
    telefono VARCHAR(45),
    email VARCHAR(45)
);

-- ==========================================
-- TABLA: clientes (compradores)
-- ==========================================
CREATE TABLE clientes (
    id_cliente INT AUTO_INCREMENT PRIMARY KEY,
    documento VARCHAR(45) NOT NULL UNIQUE,
    nombre VARCHAR(45) NOT NULL,
    telefono VARCHAR(45),
    email VARCHAR(45),
    direccion VARCHAR(45)
);

-- ==========================================
-- TABLA: productos (publicados por vendedores)
-- ==========================================
CREATE TABLE productos (
    id_producto INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(45) NOT NULL,
    descripcion VARCHAR(45),
    precio INT NOT NULL,
    stock INT NOT NULL DEFAULT 0,
    vendedor_id INT NOT NULL,
    FOREIGN KEY (vendedor_id)
        REFERENCES vendedores(id_vendedor)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
);

-- ==========================================
-- TABLA: ventas (orden/pedido)
-- ==========================================
CREATE TABLE ventas (
    id_venta INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    total INT NOT NULL,
    fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id)
        REFERENCES clientes(id_cliente)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
);

-- ==========================================
-- TABLA: carritos (detalle de la venta)
-- ==========================================
CREATE TABLE carritos (
    id_carrito INT AUTO_INCREMENT PRIMARY KEY,
    venta_id INT NOT NULL,
    producto_id INT NOT NULL,
    cantidad INT NOT NULL,
    sub_total INT NOT NULL,
    FOREIGN KEY (venta_id)
        REFERENCES ventas(id_venta)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    FOREIGN KEY (producto_id)
        REFERENCES productos(id_producto)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
);
```

### Contenedor MariaDB

Tu `docker-compose` de BD es algo como:

```yaml
version: "3.9"

services:
  db:
    build: .
    container_name: mariadb_uq
    environment:
      MYSQL_ROOT_PASSWORD: jero123;
      MYSQL_DATABASE: mydb
      MYSQL_USER: user
      MYSQL_PASSWORD: userpass
    ports:
      - "3306:3306"
    volumes:
      - /mnt/raid:/var/lib/mysql

volumes:
  db_data:
```

Para que coincida con la app:

- O bien cambias `MYSQL_DATABASE` a `marketplace`.
- O creas la base `marketplace` manualmente y corres el SQL anterior dentro del contenedor.

Ejemplo para aplicar el SQL dentro del contenedor:

```bash
docker exec -i mariadb_uq mariadb -uroot -pjero123; < schema.sql
```

> Donde `schema.sql` contiene el SQL anterior (y el contenedor se llama `mariadb_uq`).


## Configuración de conexión a la BD (PHP)

En [`config/config.php`](config/config.php:1) se define la conexión PDO:

```php
const DB_HOST = 'db';
const DB_PORT = 3306;
const DB_NAME = 'marketplace';
const DB_USER = 'root';
const DB_PASS = 'jero123;';
```

Esto asume:

- Host: el servicio Docker de MariaDB se llama `db`.
- Puerto: `3306`.
- Base de datos: `marketplace`.
- Usuario: `root` con password `jero123;`.

Puedes sobreescribirlos con variables de entorno:

- `DB_HOST`
- `DB_PORT`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`


## Endpoints de la API

Base path: `/api`

### Autenticación de vendedores

#### POST `/api/auth/login.php`

- Request (JSON):

```json
{ "documento": "123456789" }
```

- Respuestas:

  - `200 OK`:

    ```json
    {
      "success": true,
      "vendedor": {
        "id_vendedor": 1,
        "documento": "123456789",
        "nombre": "Juan",
        "telefono": "3000000000",
        "email": "juan@example.com"
      }
    }
    ```

  - `404 Not Found` si no existe el vendedor.

#### POST `/api/auth/register.php`

- Request (JSON):

```json
{
  "documento": "123456789",
  "nombre": "Juan Pérez",
  "telefono": "3000000000",
  "email": "juan@example.com"
}
```

- Respuestas:

  - `201 Created`:

    ```json
    {
      "success": true,
      "message": "Vendedor registrado correctamente.",
      "vendedor": {
        "id_vendedor": 1,
        "documento": "123456789",
        "nombre": "Juan Pérez",
        "telefono": "3000000000",
        "email": "juan@example.com"
      }
    }
    ```

  - `409 Conflict` si ya existe un vendedor con ese documento.


### Gestión de productos

#### GET `/api/productos.php?vendedor_id={id}`

- Lista los productos de un vendedor.

- Respuesta:

```json
{
  "success": true,
  "productos": [
    {
      "id_producto": 1,
      "nombre": "Producto A",
      "descripcion": "Descripción",
      "precio": 10000,
      "stock": 5,
      "vendedor_id": 1
    }
  ]
}
```

#### POST `/api/productos.php`

- Crea un nuevo producto para el vendedor.

- Request (JSON):

```json
{
  "nombre": "Producto A",
  "descripcion": "Descripción",
  "precio": 10000,
  "stock": 5,
  "vendedor_id": 1
}
```

- Respuestas:

  - `201 Created` con el producto creado.
  - `404 Not Found` si el vendedor no existe.
  - `422` si datos inválidos (precio/stock negativos o faltantes).


### Listado de ventas

#### GET `/api/ventas.php?vendedor_id={id}`

- Lista las ventas donde el vendedor tiene al menos un producto en el carrito.

- Respuesta:

```json
{
  "success": true,
  "ventas": [
    {
      "id_venta": 1,
      "fecha": "2025-11-17 10:00:00",
      "total": 50000,
      "cliente_nombre": "Cliente X",
      "items": 2
    }
  ]
}
```


## Configuración de Nginx + PHP-FPM

La idea es:

- Nginx sirve los archivos estáticos (HTML/CSS/JS) desde `/usr/share/nginx/html`.
- Para rutas `/api/*.php` envía la petición a `php-fpm` vía `fastcgi_pass`.

Ejemplo de `docker-compose.yml` para Nginx + PHP-FPM + DB (adaptable a tu entorno):

```yaml
version: "3.9"

services:
  db:
    image: mariadb:11
    container_name: mariadb_uq
    environment:
      MYSQL_ROOT_PASSWORD: jero123;
      MYSQL_DATABASE: marketplace
    volumes:
      - /mnt/raid:/var/lib/mysql
    ports:
      - "3306:3306"

  php-fpm:
    image: php:8.2-fpm
    container_name: php_fpm_marketplace
    working_dir: /usr/share/nginx/html
    volumes:
      - ./html:/usr/share/nginx/html
    environment:
      DB_HOST: db
      DB_PORT: 3306
      DB_NAME: marketplace
      DB_USER: root
      DB_PASS: jero123;
    depends_on:
      - db

  nginx:
    image: nginx:1.27-alpine
    container_name: my-nginx
    ports:
      - "80:80"
    volumes:
      - ./html:/usr/share/nginx/html:ro
      - ./nginx.conf:/etc/nginx/conf.d/default.conf:ro
    depends_on:
      - php-fpm
```

Ejemplo de `nginx.conf` (que deberías crear al lado de este README y montar como `default.conf`):

```nginx
server {
    listen 80;
    server_name _;

    root /usr/share/nginx/html;
    index index.html;

    # Frontend (SPA simple, sin routing client-side complejo)
    location / {
        try_files $uri $uri/ =404;
    }

    # API PHP: /api/*.php
    location ~ ^/api/.*\.php$ {
        root /usr/share/nginx/html;

        fastcgi_pass   php-fpm:9000;
        fastcgi_index  index.php;

        include        fastcgi_params;
        fastcgi_param  SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param  PATH_INFO       $fastcgi_path_info;
    }

    # Archivos estáticos optimizados
    location ~* \.(js|css|png|jpg|jpeg|gif|svg|ico)$ {
        try_files $uri =404;
        expires 7d;
        access_log off;
    }
}
```

Puntos clave:

- `root /usr/share/nginx/html;` coincide con el volumen `./html:/usr/share/nginx/html`.
- Los scripts PHP (`/api/*.php`) están **dentro** del mismo `root` que los estáticos.
- Nginx reenvía cualquier `/api/*.php` a `php-fpm:9000` (nombre del servicio `php-fpm` del compose).


## Flujo para probar la app

1. **Arrancar los contenedores**

   Asegúrate de tener:

   - El contenedor de MariaDB corriendo (`db` / `mariadb_uq`).
   - Los contenedores de `php-fpm` y `nginx` corriendo según tu `docker-compose`.

2. **Crear la base de datos y tablas**

   Dentro del contenedor de MariaDB:

   ```bash
   docker exec -it mariadb_uq mariadb -uroot -pjero123;
   ```

   Y dentro del cliente:

   ```sql
   SOURCE /ruta/a/schema.sql;
   ```

   O pega directamente el SQL de la sección de esquema.

3. **Acceder al frontend**

   En tu navegador: `http://localhost/`

4. **Probar login / registro**

   - Ingresa un documento de vendedor **que no exista**:
     - El sistema mostrará mensaje indicando que no existe y te llevará al formulario de registro con el documento precargado.
   - Completa el formulario de registro:
     - Al registrarte, te redirige al dashboard de vendedor.

5. **Publicar un producto**

   En la sección **"Publicar nuevo producto"**:

   - Completa: nombre, descripción, precio, stock.
   - Click en **"Publicar"**.
   - Debería:
     - Crear el producto en la tabla `productos`.
     - Refrescar la tabla de **"Mis productos publicados"**.

6. **Ver productos y ventas**

   - **Mis productos:** usa `GET /api/productos.php?vendedor_id={id_vendedor}`.
   - **Ventas de mis productos:** usa `GET /api/ventas.php?vendedor_id={id_vendedor}`.

   La tabla de ventas mostrará:

   - `ID Venta`
   - `Fecha`
   - `Cliente`
   - `Items` (número de líneas de carrito donde el producto pertenece al vendedor).
   - `Total` de la venta (campo `total` en tabla `ventas`).


## Resumen

- Frontend estático servido por Nginx (`index.html`, CSS, JS).
- Backend PHP sencillo bajo `/api/*.php` para autenticación y manejo de productos/ventas.
- MariaDB con base de datos `marketplace` y las tablas indicadas.
- Comunicación entre Nginx y PHP-FPM a través de `fastcgi_pass` usando el servicio Docker `php-fpm`.

Con esta configuración, el flujo completo de vendedor queda cubierto: login/registro, publicación de productos, visualización de catálogo propio y visualización de ventas asociadas.