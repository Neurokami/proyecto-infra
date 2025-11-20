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
  - [`apache/Dockerfile`](apache/Dockerfile:1)
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
   - Creación de la imagen de Apache en [`apache/Dockerfile`](apache/Dockerfile:1) y del sitio de
     ejemplo en [`apache/html/index.html`](apache/html/index.html:1), con la idea de servir en el futuro
     el frontend de clientes del market online.

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
  - Que existen la base de datos y las tablas del market online (clientes, productos, ventas).
  - Que se pueden hacer inserciones y consultas básicas.

### 5.2. Nginx + PHP-FPM

En la carpeta `nginx/`:

```bash
cd nginx
docker compose up -d
```

Comprobaciones:

- Acceder a la página principal en `http://localhost` y verificar que el frontend carga.
- Probar algunos endpoints de la API (por ejemplo, `/api/productos.php`, `/api/ventas.php`,
  endpoints de login y registro) y comprobar que responden correctamente usando la base de datos.

### 5.3. Apache

En la carpeta `apache/`:

```bash
cd apache
docker compose up -d
```

Comprobaciones:

- Acceder a `http://localhost:8080` y visualizar la página de prueba servida por Apache.
- Verificar que el contenido se sirve desde el volumen configurado (LVM asociado a Apache).

### 5.4. Netdata

En la carpeta `netdata/`:

```bash
cd netdata
docker compose up -d
```

Comprobaciones:

- Acceder a `http://localhost:19999` en el navegador.
- Verificar que el dashboard muestra:
  - Recursos del host (CPU, RAM, disco, red).
  - Contenedores Docker y sus métricas (IO, CPU, memoria).

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
- Apache: [`apache/Dockerfile`](apache/Dockerfile:1)
- Netdata: [`netdata/Dockerfile`](netdata/Dockerfile:1)

## 8. Cómo añadir imágenes (capturas) a la bitácora

Para documentar el proyecto con capturas de pantalla o diagramas, se recomienda:

1. **Crear la carpeta de imágenes**

   En la raíz del repositorio, crea una carpeta llamada `img` (si aún no existe):

   ```bash
   mkdir -p img
   ```

2. **Guardar las capturas en `img/`**

   Copia tus imágenes dentro de `img/` y usa nombres descriptivos, por ejemplo:

   - `img/netdata-dashboard.png`
   - `img/docker-ps.png`
   - `img/raid-config.png`
   - `img/market-frontend.png`

3. **Referenciar las imágenes en el README.md**

   Desde este `README.md` (que está en la raíz del repo), las rutas a las imágenes deben ser relativas:

   ```markdown
   ![Netdata dashboard](img/netdata-dashboard.png)
   ![Contenedores en ejecución](img/docker-ps.png)
   ```

   - El texto entre corchetes (`[...]`) es el **texto alternativo** (útil para accesibilidad).
   - La ruta entre paréntesis (`(...)`) apunta a la imagen dentro de `img/`.

De esta forma puedes ir agregando evidencias visuales (panel de Netdata, salidas de `docker compose ps`,
capturas de las páginas de Apache/Nginx, etc.) sin modificar la estructura del proyecto ni la
configuración de los contenedores.
