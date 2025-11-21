# proyecto-infra
Proyecto final – Infraestructura Computacional

Este repositorio contiene la solución del proyecto final de infraestructura computacional.
El objetivo es consolidar los servicios de una organización en un único servidor usando
contenedores (Docker y Podman), almacenamiento confiable basado en RAID1 + LVM y
monitorización en tiempo real con Netdata.

La bitácora se integra directamente en este `README.md` y resume:
- Los pasos realizados.
- La configuración de RAID y LVM.
- La creación y uso de imágenes a partir de `Dockerfile` (también válidos como `Containerfile`).
- Las pruebas de funcionamiento de los servicios y de la persistencia de datos.
- El uso de Netdata para monitorizar el host y los contenedores.
- Cómo añadir imágenes (capturas) a la documentación.

## 1. Resumen del proyecto y contexto

La organización contaba con varios servidores físicos tipo torre y adquirió un nuevo servidor con
mayores capacidades para centralizar y virtualizar sus servicios. Sobre este servidor se implementa:

- Virtualización basada en contenedores (Docker y Podman).
- Tres servicios principales:
  - Apache (sitio web para clientes).
  - MariaDB/MySQL (base de datos del market online).
  - Nginx + PHP-FPM (frontend/API para vendedores).
- Un servicio adicional de monitorización:
  - Netdata (monitorización en tiempo real del host y de los contenedores).
- Almacenamiento con RAID1 + LVM para asegurar disponibilidad y persistencia de datos.

Las imágenes de los contenedores se construyen con archivos `Dockerfile` ubicados en este repositorio
y pueden ser usadas también como `Containerfile` al trabajar con Podman (mismo formato).

## 2. Servicios y contenedores

La estructura de carpetas del proyecto, a alto nivel, es:

```text
.
├── base-datos/              # Contenedor MariaDB (antes MySQL)
├── nginx/                   # Contenedor Nginx + PHP-FPM (frontend + API PHP)
├── netdata/                 # Contenedor Netdata (monitorización)
├── apache/                  # Contenedor Apache (sitio para clientes)
└── raid-lvm/                # Scripts de RAID1 + LVM en el host
```

### 2.1. Base de datos – MariaDB (MySQL compatible)

- Propósito: almacenar la información del market online (clientes, productos, ventas y detalle de compra).
- Imagen personalizada basada en Alpine:
  - [`base-datos/Dockerfile`](base-datos/Dockerfile:1)
- Configuración y scripts:
  - [`base-datos/my.cnf`](base-datos/my.cnf:1)
  - [`base-datos/docker-entrypoint.sh`](base-datos/docker-entrypoint.sh:1)
  - [`base-datos/marketdb.txt`](base-datos/marketdb.txt:1)
- Orquestación con Docker Compose:
  - [`base-datos/docker-compose.yml`](base-datos/docker-compose.yml:1), donde se definen las variables
    de entorno (root password, usuario, base de datos), el puerto `3306:3306` y el volumen de datos
    persistentes vinculado al LVM de base de datos.

Esta misma definición de imagen se puede usar con Podman simplemente cambiando el comando
de build/run (el archivo `Dockerfile` es compatible con `Containerfile`).

### 2.2. Nginx + PHP-FPM – Interfaz de vendedores y API

- Propósito:
  - Proveer una interfaz web para vendedores.
  - Exponer una API PHP que consume la base de datos de ventas.
- Imagen multi-stage con dos *targets* (PHP-FPM y Nginx):
  - [`nginx/Dockerfile`](nginx/Dockerfile:1)
- Configuración de Nginx:
  - [`nginx/nginx.conf`](nginx/nginx.conf:1)
- Código frontend + API PHP:
  - HTML/CSS/JS + PHP en `nginx/html/`, por ejemplo:
    - [`nginx/html/index.html`](nginx/html/index.html:1)
    - [`nginx/html/api/bootstrap.php`](nginx/html/api/bootstrap.php:1)
    - [`nginx/html/config/config.php`](nginx/html/config/config.php:1)
- Orquestación con Docker Compose:
  - [`nginx/docker-compose.yml`](nginx/docker-compose.yml:1), que define los servicios `php` y `nginx`,
    los volúmenes de código y configuración y la publicación del puerto HTTP.

Los mismos contenidos pueden utilizarse con Podman usando el `Dockerfile` como `Containerfile`
(en Podman se puede usar `-f Dockerfile` sin cambios en el archivo).

### 2.3. Apache – Sitio para clientes

> Nota: este servicio está preparado y documentado, aunque su implementación funcional completa
> para el frontend de clientes está en progreso.

- Propósito previsto:
  - Servir el frontend de clientes del market: registro, visualización de productos, compras, etc.
- Imagen de Apache:
  - [`apache/Dockerfile`](apache/docker/apache/Dockerfile:1)
- Sitio web servido por Apache:
  - [`apache/html/index.html`](apache/html/index.html:1)
- Configuración principal:
  - [`apache/config/httpd.conf`](apache/config/httpd.conf:1)
- Orquestación con Docker Compose:
  - [`apache/docker-compose.yml`](apache/docker-compose.yml:1), que publica Apache en el puerto `8080`
    del host y monta volúmenes desde el LVM asociado a Apache.

### 2.4. Netdata – Monitorización

- Propósito:
  - Monitorizar en tiempo real el host, los contenedores Docker y sus recursos.
- Imagen:
  - [`netdata/Dockerfile`](netdata/Dockerfile:1) basada en la imagen oficial `netdata/netdata:stable`.
- Orquestación:
  - [`netdata/docker-compose.yml`](netdata/docker-compose.yml:1), donde se configuran:
    - Puerto `19999` para la interfaz web.
    - Montajes de volúmenes del sistema (`/proc`, `/sys`, `/etc/os-release`, etc.).
    - Montaje de `/var/run/docker.sock` para leer información de los contenedores Docker.

## 3. Persistencia con RAID y LVM (resumen)

Para cumplir con los requerimientos del proyecto se plantean **3 arreglos RAID1**, uno por cada
tipo de servicio principal:

- RAID1 + LVM para base de datos (datos de MariaDB).
- RAID1 + LVM para Nginx (código HTML/PHP y configuración).
- RAID1 + LVM para Apache (código del sitio para clientes).

El flujo general seguido fue:

1. **Implementación manual de RAID + LVM**
   - Uso de `mdadm` para crear el RAID:
     - Ver discos con `lsblk`.
     - Crear RAID con `mdadm --create ...`.
     - Ver su estado con `watch cat /proc/mdstat`.
   - Crear sistema de archivos (`mkfs.ext4`) y montar en `/mnt/raid` (o equivalentes).
   - Configurar el arranque automático:
     - Añadir la definición del RAID a `/etc/mdadm/mdadm.conf`.
     - Actualizar `initramfs` con `update-initramfs -u`.
     - Añadir la entrada a `/etc/fstab` utilizando el `UUID`.
   - Crear LVM sobre el RAID:
     - `pvcreate`, `vgcreate`, `lvcreate`.
     - Formatear y montar el LV.
     - Añadir la entrada correspondiente a `/etc/fstab` usando el `UUID` del LV.

2. **Automatización de estos pasos en scripts reutilizables**
   - Scripts ubicados en `raid-lvm/`:
     - [`raid-lvm/percistencia_db.sh`](raid-lvm/percistencia_db.sh:1) → RAID1 + LVM para base de datos.
     - [`raid-lvm/percistencia_nginx.sh`](raid-lvm/percistencia_nginx.sh:1) → RAID1 + LVM para Nginx.
     - [`raid-lvm/percistencia_apache.sh`](raid-lvm/percistencia_apache.sh:1) → RAID1 + LVM para Apache.
   - Cada script realiza automáticamente:
     - Instalación de `mdadm` y `lvm2` (si hace falta).
     - Creación del dispositivo RAID1 (`/dev/mdX`).
     - Creación del volumen físico LVM (`pvcreate`), grupo de volúmenes (`vgcreate`) y volumen lógico (`lvcreate`).
     - Formateo del LV en `ext4`, montaje en el punto correspondiente
       (por ejemplo `/mnt/basedatos`, `/mnt/nginx`, `/mnt/apache`).
     - Añadir la entrada en `/etc/fstab` para que el volumen se monte automáticamente al iniciar.

## 4. Bitácora de pasos realizados (resumen)

A nivel cronológico, el trabajo se puede resumir así:

1. **Diseño del contexto del proyecto**
   - Definición del escenario de la organización, el nuevo servidor y la decisión de utilizar
     contenedores Docker/Podman para los servicios web y de base de datos.

2. **Creación manual de RAID + LVM**
   - Ejecución de los pasos manuales (mdadm, creación de sistema de archivos, configuración de `fstab`,
     instalación de `lvm2`, creación de PV/VG/LV) siguiendo el procedimiento descrito en la sección de
     persistencia.

3. **Automatización de la persistencia**
   - Empaquetado de esos pasos en los scripts:
     [`raid-lvm/percistencia_db.sh`](raid-lvm/percistencia_db.sh:1),
     [`raid-lvm/percistencia_nginx.sh`](raid-lvm/percistencia_nginx.sh:1) y
     [`raid-lvm/percistencia_apache.sh`](raid-lvm/percistencia_apache.sh:1),
     para poder recrear rápidamente los RAID1 y LVM en nuevas instalaciones.

4. **Creación de la imagen de base de datos**
   - Construcción de la imagen basada en Alpine con MariaDB usando
     [`base-datos/Dockerfile`](base-datos/Dockerfile:1) y preparación de la base de datos del
     market online (clientes, productos, ventas) a partir de los archivos en `base-datos/`.

5. **Implementación de Nginx + PHP-FPM para vendedores**
   - Configuración del contenedor multi-stage en [`nginx/Dockerfile`](nginx/Dockerfile:1) y
     añadido del código del frontend/API en `nginx/html/` para que los vendedores puedan registrarse
     y publicar productos, interactuando con la base de datos de ventas.

6. **Preparación del contenedor Apache para clientes**
   - Creación de la imagen de Apache (httpd) haciendo uso de la herramienta `compose` de docker y una pequeña prueba de funcionamiento usando laravel.

7. **Despliegue de Netdata**
   - Añadido de Netdata usando [`netdata/Dockerfile`](netdata/Dockerfile:1) y
     [`netdata/docker-compose.yml`](netdata/docker-compose.yml:1) para monitorizar tanto el host como
     los contenedores del proyecto en tiempo real.

## 5. Pruebas de funcionamiento

A continuación se resume cómo se probaron los servicios y la persistencia de datos.

### 5.1. Base de datos (MariaDB)

En la carpeta `base-datos/`:

```bash
cd base-datos
docker compose up -d
```

Comprobaciones:

- Verificaccion que el contenedor `mariadb_uq` está en estado `running`.
  ![Comprobacion de ejcucion de la base de datos con dokcer ps](./imgs/db-ejecucion.jpg)
- Conectarse a la base de datos y comprobar:
  - Entrar a la base de datos.
  ![Entrando a la base de datos por teminal](./imgs/db-adentro-sh.jpg)
  - Creacion de una base de datos market.
  ![Creacion de la base de datos](./imgs/db-creacion.jpg)
  - Crear tablas para el negocio.
  ![Creacion de tablas](./imgs/db-tablas.jpg)
  - Comprobar insersion de datos a las tablas.
  ![Insertar datos en las tablas](./imgs/db-insert.jpg)
  - Comprobar existencia de la base de datos y las tablas del market (clientes, productos, ventas).
  ![Comprobacion de datos](./imgs/db-comprobacion.jpg)
  -Descripcion de la tablas.
  ![Descripcion de tablas](./imgs/db-descripcion.jpg)
  - Comprobar persistencia de datos.
  ![Comprobacion de persistencia](./imgs/db-persistencia.jpg)

### 5.2. Nginx + PHP-FPM

En la carpeta `nginx/`:

```bash
cd nginx
docker compose up -d
```

Comprobaciones:
- Verificaccion que el contenedor  `nginx` esta en estado `running`.
![Comprobacion de ejecucion de nginx](./imgs/nginx-ejecucion.jpg)
- Acceder a la página principal en `http://localhost` y verificar que el frontend carga.
![Comprobacion de interfas](./imgs/nginx-ui.jpg)
- Probar algunos endpoints de la API.
  -Registro de clientes.
  ![Registro de clientes](./imgs/nginx-registro.jpg)
  -Login de clientes.
  ![Login de clientes](./imgs/nginx-login.jpg)
  -Pagina del vendedor.
  ![Pagina del vendedor](./imgs/nginx-ui-vendedor.jpg)
  -Comprobacion de persistencia.
  ![Comprobacion de persistencia](./imgs/nginx-persistencia.jpg)
  -Creacion de productos.
  ![Creacion de productos](./imgs/nginx-producto.jpg)
  ![Refresco de pagina](./imgs/nginx-producto-1.jpg)
  -comprobaccion de persistencia de productos.
  ![Comprobacion de persistencia de productos](./imgs/nginx-persistencia-producto.jpg)
  
### 5.3. Apache

El apartado concerniente a apache se encuentra formado por 2 contenedores, uno para php-fpm y otro httpd.

La aplicacion es un ejemplo sencillo de comunicacion entre contenedores usando el laravel. Del mismo modo que se realizo con el servicio de nginx se hace uso de una red puente para conectar el contenedor de php con el de la base de datos.

En la carpeta `apache/`:

```bash
cd apache
docker compose up -d
```

**Comprobaciones:**

- Acceder a `http://localhost:8080` y visualizar la página de bienvenida para la aplicacion de prueba

![Comprobacion pagina de inicio apache](./imgs/apache-inicio.png)

- Acceder al apartado de productos y verificar si hay algun producto

![Comprobacion pagina de productos apache](./imgs/apache-productos-vacio.png)

- Crear un producto en el contenedor de la base de datos (puede ser usando la aplicacion de nginx) y comprobar nuevamente el apartado de productos

![Comprobacion pagina de productos apache](./imgs/apache-productos.png)

### 5.4. Netdata

Para monitorizar el sistema se hizo uso de la herramienta Netdata esta tambien se encuentra montada en un contenedor.

En la carpeta `netdata/`:

```bash
cd netdata
docker compose up -d
```

**Comprobaciones:**

- Acceder a `http://localhost:19999` en el navegador y comprobar la pagina de inicio de netdata.

![comprobacion inicio netdata](./imgs/net-data-info.png)

- verificar en el dashboard las metricas que nos propociona la herramienta (CPU, RAM, disco, red)

![comprobacion inicio netdata](./imgs/net-data-metricas.png)

- verificar que la herramienta este detectando correctamente los contenedores

![comprobacion inicio netdata](./imgs/net-data-cgroups.png)


### 5.5. Verificación de la persistencia

Para comprobar la persistencia usando RAID + LVM:

1. Identificar un directorio montado desde el LVM correspondiente (por ejemplo, el directorio
   de datos de MariaDB o el directorio de HTML de Nginx/Apache).
2. Modificar un archivo relevante (por ejemplo, cambiar el contenido de una página HTML
   o insertar datos en la base de datos).
3. Reiniciar el contenedor:

```bash
docker compose restart
```

4. Verificar que el cambio sigue presente después del reinicio:
   - La página HTML mantiene el cambio.
   - La información en la base de datos sigue disponible.

Con esto se demuestra que los volúmenes vinculados a los LVM preservan los datos.

## 6. Netdata contenerizado (resumen del examen práctico)

En el contexto del examen de Netdata, se evaluó:

- Uso de la imagen oficial `netdata/netdata:stable` (referenciada en
  [`netdata/Dockerfile`](netdata/Dockerfile:1)).
- Despliegue de Netdata como contenedor:
  - Exposición del puerto `19999`.
  - Montaje de los volúmenes necesarios para monitorizar:
    - Archivos del sistema (`/proc`, `/sys`, `/etc/os-release`, etc.).
    - Información de usuarios y grupos.
    - `docker.sock` para ver contenedores Docker.
- Verificación en la interfaz de Netdata de:
  - Métricas en tiempo real del servidor.
  - Listado y métricas de los contenedores del proyecto (Apache, MariaDB, Nginx, etc.).

## 7. Uso de Dockerfile también como Containerfile (Podman)

Los archivos `Dockerfile` de este proyecto son compatibles con Podman.
Para usarlos como `Containerfile` no es necesario cambiar el contenido, solo el comando:

- Ejemplo con Docker:

```bash
docker build -t mi-imagen-base-datos -f Dockerfile .
```

- Ejemplo equivalente con Podman:

```bash
podman build -t mi-imagen-base-datos -f Dockerfile .
```

Esto se aplica a las imágenes de:

- Base de datos: [`base-datos/Dockerfile`](base-datos/Dockerfile:1)
- Nginx + PHP-FPM: [`nginx/Dockerfile`](nginx/Dockerfile:1)
- Apache: [`apache/docker/apache/Dockerfile`](apache/Dockerfile:1)
- Netdata: [`netdata/Dockerfile`](netdata/Dockerfile:1)
