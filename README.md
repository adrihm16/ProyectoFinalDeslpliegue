# MicroBlog — Sistema de Blog con Docker

Proyecto final de la asignatura **Despliegue de Aplicaciones Web** del ciclo de 2º DAW.

Se trata de un sistema de blog completo construido con una arquitectura de microservicios, donde cada parte de la aplicación (frontend, API, base de datos, caché y proxy) vive en su propio contenedor Docker. El objetivo principal es aprender a desplegar, orquestar y escalar aplicaciones reales usando Docker y Docker Compose.

---

## Índice

1. [Arquitectura del sistema](#-arquitectura-del-sistema)
2. [Descripción de los servicios](#-descripción-de-los-servicios)
3. [Flujo de datos](#-flujo-de-datos)
4. [Requisitos previos](#-requisitos-previos)
5. [Instalación y puesta en marcha](#-instalación-y-puesta-en-marcha)
6. [Instrucciones de uso](#-instrucciones-de-uso)
7. [Escalado horizontal](#-escalado-horizontal)
8. [Seguridad](#-seguridad)
9. [Optimización de imágenes](#-optimización-de-imágenes)
10. [Troubleshooting](#-troubleshooting)
11. [Guía de rollback](#-guía-de-rollback)
12. [Decisiones de diseño](#-decisiones-de-diseño)
13. [Preguntas de reflexión](#-preguntas-de-reflexión)

---

## Arquitectura del sistema

La aplicación sigue una **arquitectura de microservicios** en la que cada componente es un contenedor independiente. Todos se comunican a través de redes internas de Docker y el usuario solo accede a través de Nginx, que actúa como punto de entrada único.
(Diagrama adjunto en el pdf)

### Redes

La infraestructura utiliza **dos redes separadas** por motivos de seguridad:

- **`frontend_net`**: conecta Nginx con los servicios que necesitan recibir peticiones del exterior (frontend, API y phpMyAdmin).
- **`backend_net`**: conecta los servicios de aplicación con la base de datos y la caché. Es una red privada a la que Nginx no tiene acceso directo.

De esta forma, un atacante que consiguiera comprometer el proxy no podría acceder directamente a la base de datos.

---

## Descripción de los servicios

| Servicio | Imagen | Puerto interno | Red(es) | Función |
|---|---|---|---|---|
| **proxy** | `nginx:alpine` | 80 | frontend_net | Punto de entrada. Recibe todas las peticiones HTTP y las redirige al servicio correspondiente. También balancea la carga entre las réplicas del frontend. |
| **frontend** | `adrihm95/microblog-frontend:v1.1` | 9000 | frontend_net, backend_net | Sirve la interfaz web del blog usando PHP-FPM. Consulta la base de datos y utiliza Redis como caché. Puede escalarse a múltiples réplicas. |
| **api** | `adrihm95/microblog-api:v1.0` | 8000 | frontend_net, backend_net | API REST que expone los datos del blog en formato JSON. Permite consultar y crear posts de forma programática. |
| **db** | `mariadb:10.11` | 3306 | backend_net | Base de datos relacional. Almacena usuarios, posts y comentarios. Los datos persisten en un volumen Docker. |
| **redis** | `redis:alpine` | 6379 | backend_net | Sistema de caché en memoria. Almacena temporalmente las consultas más frecuentes para que la aplicación sea más rápida y no tenga que consultar la base de datos constantemente. |
| **phpmyadmin** | `phpmyadmin:latest` | 80 | frontend_net, backend_net | Herramienta de administración visual para la base de datos. Accesible desde `/phpmyadmin/`. |

---

## Flujo de datos

Para entender cómo se mueve la información en el sistema, vamos a seguir el recorrido de una petición típica:

1. **El usuario** escribe `http://localhost` en su navegador.
2. **Nginx** recibe la petición en el puerto 80 y mira la URL:
   - Si es una ruta normal (`/`, `/post.php`), la reenvía al servicio **frontend** por FastCGI (puerto 9000).
   - Si empieza por `/api/`, la reenvía al servicio **api** por HTTP (puerto 8000).
   - Si empieza por `/phpmyadmin/`, la reenvía a **phpMyAdmin**.
3. **El frontend** (o la API) primero comprueba en **Redis** si la información que necesita ya está cacheada:
   - **Si está en caché** (cache hit): devuelve los datos directamente sin tocar la base de datos. Esto es mucho más rápido.
   - **Si no está** (cache miss): consulta a **MariaDB**, guarda el resultado en Redis durante 5 minutos, y luego devuelve la respuesta.
4. **Nginx** recibe la respuesta del servicio y se la entrega al usuario.

---

## Requisitos previos

Antes de empezar necesitas tener instalado:

- **Docker Desktop** (versión 20.10 o superior) — [Descargar aquí](https://www.docker.com/products/docker-desktop/)
- **Docker Compose** (normalmente viene incluido con Docker Desktop)
- **Git** para clonar el repositorio

Para comprobar que todo está instalado correctamente:

```bash
docker --version
docker compose version
git --version
```

---

## Instalación y puesta en marcha

### Paso 1: Clonar el repositorio

```bash
git clone https://github.com/adrihm16/ProyectoFinalDeslpliegue.git
cd ProyectoFinalDeslpliegue
```

### Paso 2: Configurar las variables de entorno

El archivo `.env` contiene las contraseñas y configuraciones sensibles. Como no se sube al repositorio (está en `.gitignore`), tienes que crearlo manualmente:

```bash
# Crea el archivo .env en la raíz del proyecto con este contenido:
```

```ini
# Base de Datos
MYSQL_ROOT_PASSWORD=tu_contraseña_root_segura
MYSQL_DATABASE=blogdb
MYSQL_USER=bloguser
MYSQL_PASSWORD=tu_contraseña_segura

# Configuración del Frontend
DB_HOST=db
DB_NAME=blogdb
DB_USER=bloguser
DB_PASS=tu_contraseña_segura
DB_PASSWORD=tu_contraseña_segura
REDIS_HOST=redis
REDIS_PORT=6379

# phpMyAdmin
PMA_HOST=db
PMA_USER=bloguser
PMA_PASSWORD=tu_contraseña_segura

# Puertos
PROXY_PORT=80
PHPMYADMIN_PORT=8081
```

> **Importante:** Cambia las contraseñas de ejemplo por contraseñas seguras. Nunca subas el archivo `.env` a GitHub.

### Paso 3: Construir y levantar los servicios

```bash
docker compose up -d --build
```

Este comando:
- Construye las imágenes personalizadas del frontend y la API.
- Descarga las imágenes oficiales (Nginx, MariaDB, Redis, phpMyAdmin).
- Crea las redes y los volúmenes.
- Levanta todos los contenedores en segundo plano.

### Paso 4: Verificar que todo funciona

```bash
docker compose ps
```

Deberías ver todos los servicios con estado `Up` y `(healthy)`:

```
NAME         STATUS              PORTS
blog_proxy   Up (healthy)        0.0.0.0:80->80/tcp
blog_api     Up (healthy)        8000/tcp
blog_db      Up (healthy)        3306/tcp
blog_redis   Up (healthy)        6379/tcp
blog_pma     Up                  80/tcp
```

### Paso 5: Acceder a la aplicación

- **Blog principal:** [http://localhost](http://localhost)
- **API REST:** [http://localhost/api/posts](http://localhost/api/posts)
- **Panel de monitoreo:** [http://localhost/monitor.php](http://localhost/monitor.php)
- **phpMyAdmin:** [http://localhost/phpmyadmin/](http://localhost/phpmyadmin/)

---

## Instrucciones de uso

### Blog

La página principal muestra todos los posts publicados. Puedes hacer clic en "Leer más" para ver el contenido completo de cada post y sus comentarios.

### API REST

La API expone los datos del blog en formato JSON. Los endpoints disponibles son:

| Método | Endpoint | Descripción |
|---|---|---|
| `GET` | `/api/posts` | Obtener todos los posts |
| `GET` | `/api/posts/{id}` | Obtener un post concreto con sus comentarios |
| `POST` | `/api/posts` | Crear un nuevo post |
| `GET` | `/api/stats` | Obtener estadísticas del sistema |

Ejemplo de uso con curl:

```bash
# Ver todos los posts
curl http://localhost/api/posts

# Crear un nuevo post
curl -X POST http://localhost/api/posts \
  -H "Content-Type: application/json" \
  -d '{"user_id": 1, "title": "Mi post", "content": "Contenido del post"}'
```

### Panel de monitoreo

Accesible en `/monitor.php`, muestra en tiempo real:

- Número total de peticiones recibidas (frontend y API por separado).
- Tiempo medio de respuesta.
- Tasa de aciertos de la caché (cache hit rate).
- Estado de los servicios (MySQL, Redis, API).
- Datos de la aplicación (posts, usuarios, comentarios).

---

## Escalado horizontal

El servicio frontend se puede escalar a múltiples réplicas para soportar más tráfico. Nginx reparte automáticamente las peticiones entre todas las réplicas usando un sistema llamado Round Robin (básicamente va por turnos).

```bash
# Escalar a 3 réplicas
docker compose up -d --scale frontend=3

# Verificar que las 3 réplicas están corriendo
docker compose ps
```

Cuando lo hagas, verás algo así:

```
proyectofinaldespliegue-frontend-1   Up (healthy)   9000/tcp
proyectofinaldespliegue-frontend-2   Up (healthy)   9000/tcp
proyectofinaldespliegue-frontend-3   Up (healthy)   9000/tcp
```

Si recargas la página del blog varias veces y te fijas en la sección "Información del Sistema", verás que el campo **Host** cambia entre distintos IDs de contenedor. Eso significa que Nginx está repartiendo las peticiones correctamente.

> **Nota técnica:** Para poder escalar, se ha eliminado el `container_name` fijo del servicio frontend. Si no se hace esto, Docker da error porque no puede haber dos contenedores con el mismo nombre.

---

## Seguridad

Se han implementado varias medidas de seguridad:

### Redes segmentadas

La base de datos y Redis están aislados en la red `backend_net`. El proxy Nginx solo tiene acceso a la red `frontend_net`, así que no puede comunicarse directamente con la base de datos.

### Usuario no root

Tanto el Dockerfile del frontend como el de la API utilizan la directiva `USER www-data`. Esto hace que el proceso PHP se ejecute como un usuario sin privilegios, en vez de como root. Si alguien consiguiera explotar una vulnerabilidad en el código PHP, no tendría permisos de administrador dentro del contenedor.

### Contraseñas seguras

Las contraseñas se gestionan a través de variables de entorno en el archivo `.env`, que está incluido en el `.gitignore` para que nunca se suba al repositorio.

### .dockerignore

Cada servicio tiene su `.dockerignore` para evitar que archivos innecesarios o sensibles se incluyan en la imagen Docker.

---

## Optimización de imágenes

Se ha optimizado el Dockerfile del frontend usando **multi-stage builds**. La idea es sencilla: en una primera etapa se compilan todas las extensiones de PHP (redis, pdo_mysql...) y en una segunda etapa se crea la imagen final copiando solo lo necesario, sin arrastrar los compiladores y herramientas de desarrollo.

### Resultado:

| Versión | Método | Tamaño en disco | Tamaño real |
|---|---|---|---|
| v1.0 | Single-stage | 581 MB | 148 MB |
| **v1.1** | **Multi-stage** | **122 MB** | **33.9 MB** |

Esto supone una **reducción del 77%** en el tamaño de la imagen.

---

## Troubleshooting

### Los contenedores no arrancan

```bash
# Ver los logs de todos los servicios
docker compose logs

# Ver los logs de un servicio concreto
docker compose logs frontend
docker compose logs db
```

### Error de conexión a la base de datos

Normalmente ocurre porque MariaDB aún no ha terminado de inicializarse. Espera unos 15-20 segundos después de levantar los servicios y recarga la página. También puedes comprobar el estado del healthcheck:

```bash
docker compose ps
```

Si `db` aparece como `(health: starting)`, solo hay que esperar un poco.

### El puerto 80 ya está en uso

Cambia el valor de `PROXY_PORT` en el archivo `.env`:

```ini
PROXY_PORT=8080
```

Y luego reinicia los servicios:

```bash
docker compose down
docker compose up -d
```

### Redis no conecta

Comprueba que el contenedor de Redis está levantado y sano:

```bash
docker exec blog_redis redis-cli ping
```

Debería responder `PONG`.

### Limpiar todo y empezar de cero

```bash
# Parar y eliminar contenedores, redes y volúmenes
docker compose down -v

# Reconstruir todo desde cero
docker compose up -d --build
```

> **Cuidado:** El flag `-v` elimina los volúmenes, por lo que se perderán todos los datos de la base de datos.

---

## Guía de rollback

Si algo sale mal después de hacer cambios, estos son los pasos para volver atrás:

### Volver a la versión anterior de una imagen

```bash
# Cambiar la imagen en docker-compose.yml a la versión anterior
# Por ejemplo, de v1.1 a v1.0

# Luego aplicar el cambio
docker compose up -d
```

### Restaurar la base de datos

Si la base de datos se ha corrompido o se han perdido datos:

```bash
# Eliminar el volumen de la base de datos
docker compose down
docker volume rm proyectofinaldespliegue_db_data

# Levantar de nuevo (se ejecutará el init.sql automáticamente)
docker compose up -d
```

### Volver a la configuración anterior

Si has roto el `docker-compose.yml` o algún archivo de configuración:

```bash
# Deshacer los cambios con Git
git checkout -- docker-compose.yml
git checkout -- nginx.conf

# Aplicar
docker compose up -d
```

---

## Decisiones de diseño

- **PHP-FPM en vez de Apache:** Se ha elegido PHP-FPM porque es más eficiente y ligero para manejar peticiones PHP. Apache incluye muchos módulos que no necesitamos y consume más recursos. Con FPM + Nginx tenemos un combo rápido y moderno.

- **Alpine Linux como base:** Todas las imágenes usan la variante Alpine, que es una distribución de Linux extremadamente ligera (apenas 5 MB). Esto reduce el tamaño de las imágenes y la superficie de ataque.

- **Redis como caché:** En vez de hacer consultas SQL cada vez que un usuario carga la página, los resultados se guardan en Redis durante 5 minutos. Si otro usuario pide lo mismo, la respuesta se sirve directamente desde memoria, que es mucho más rápido.

- **Nginx como proxy inverso:** En lugar de exponer cada servicio en un puerto diferente, Nginx actúa como punto de entrada único y redirige las peticiones según la URL. Esto simplifica la configuración y permite añadir balanceo de carga fácilmente.

- **Multi-stage builds:** Se usa este patrón para separar las herramientas de compilación (que solo se necesitan para construir la imagen) del entorno de ejecución final. El resultado es una imagen mucho más pequeña y segura.

---

## Preguntas de reflexión

### Sobre la arquitectura

**¿Por qué elegiste esta arquitectura?**

Elegí la arquitectura de microservicios porque permite separar cada responsabilidad en un contenedor independiente. El frontend se encarga de la interfaz, la API de exponer los datos, MariaDB de almacenarlos y Redis de acelerar el acceso a ellos. De esta forma, si un servicio falla, los demás pueden seguir funcionando (por ejemplo, si la API se cae, el blog sigue mostrando los posts). Además, era un requisito del proyecto y me pareció la mejor forma de aprender cómo funciona un despliegue real.

**¿Qué beneficios tiene sobre una arquitectura monolítica?**

En un monolito todo el código está empaquetado junto: si necesitas actualizar un módulo, tienes que redesplegar toda la aplicación. Con microservicios puedo actualizar solo la API sin tocar el frontend, o escalar solo el frontend si hay mucho tráfico sin necesidad de duplicar la base de datos. También permite que cada servicio use la tecnología más adecuada para su trabajo (PHP-FPM para el frontend, PHP-CLI para la API, MariaDB para datos relacionales, Redis para caché en memoria).

**¿Qué desafíos presenta?**

El principal desafío es la complejidad de la configuración. En un monolito tendrías un solo archivo de configuración y un solo servicio que arrancar. Aquí hay que configurar correctamente las redes, los volúmenes, las variables de entorno, los healthchecks y las dependencias entre servicios. También hay que tener en cuenta la comunicación entre contenedores, los errores de red internos y la depuración, que es más complicada cuando hay varios servicios implicados.

**¿Cómo la mejorarías para producción?**

Para un entorno de producción real añadiría: certificados SSL/TLS en Nginx para usar HTTPS, un sistema de logs centralizado como ELK (Elasticsearch + Logstash + Kibana) o Grafana + Loki, un orquestador como Docker Swarm o Kubernetes para gestionar mejor el escalado y la alta disponibilidad, backups automáticos de la base de datos, y un CI/CD (integración y despliegue continuos) para automatizar el proceso de construcción y despliegue de las imágenes.

---

### Sobre Docker

**¿Qué ventajas ofrece Docker para este proyecto?**

Docker nos permite empaquetar cada servicio con todas sus dependencias en un contenedor aislado. Esto elimina el problema de "en mi máquina funciona": cualquier persona que clone el repositorio y ejecute `docker compose up` tendrá exactamente el mismo entorno. También facilita el escalado (levantar más réplicas del frontend con un solo comando) y la limpieza (un `docker compose down -v` y vuelves a empezar de cero).

**¿Qué aprendiste sobre construcción de imágenes?**

Aprendí que el orden de las instrucciones en el Dockerfile importa mucho para aprovechar la caché de Docker. Las capas que cambian con más frecuencia (como el `COPY` del código fuente) deben ir al final. También descubrí los multi-stage builds, que permiten compilar en una etapa y copiar solo los binarios necesarios a la imagen final, reduciendo el tamaño de la imagen un 77% en nuestro caso. Y la importancia de usar `.dockerignore` para no incluir archivos innecesarios en el contexto de construcción.

**¿Qué dificultades encontraste?**

La mayor dificultad fue la configuración de redes y la comunicación entre contenedores. Por ejemplo, Nginx necesita resolver el nombre del servicio `frontend` para enviarle las peticiones, pero si el contenedor aún no está listo, Nginx falla al arrancar. Se solucionó usando resolución dinámica con variables en Nginx (`set $upstream frontend:9000`). Otro problema fue el escalado: tuve que eliminar el `container_name` fijo del frontend porque Docker no permite tener dos contenedores con el mismo nombre.

**¿Cómo las resolviste?**

Con paciencia y leyendo los logs (`docker compose logs`). La mayoría de errores se solucionaron ajustando las dependencias con `depends_on`, usando resolución DNS dinámica en Nginx y configurando correctamente los healthchecks para asegurarme de que cada servicio estaba realmente listo antes de que otros intentaran comunicarse con él.

---

### Sobre persistencia

**¿Cómo garantizas que los datos no se pierden?**

Los datos de la base de datos se almacenan en un volumen Docker llamado `db_data`, y los datos de Redis en otro llamado `redis_data`. Los volúmenes de Docker viven fuera de los contenedores, así que aunque pares o elimines un contenedor, los datos siguen ahí. Solo se pierden si eliminas el volumen explícitamente con el flag `-v`.

**¿Qué estrategia de backup implementarías?**

Implementaría un script que se ejecute automáticamente cada noche con un cron job. El script haría un `mysqldump` de toda la base de datos y lo guardaría comprimido en un directorio de backups, manteniendo los últimos 7 días. También haría un `redis-cli BGSAVE` para guardar un snapshot de la caché. Ejemplo:

```bash
docker exec blog_db mysqldump -u root -p blogdb > backup_$(date +%Y%m%d).sql
```

**¿Cómo migrarías datos entre entornos?**

Exportaría la base de datos con `mysqldump` en el entorno origen, copiaría el archivo `.sql` al entorno destino, y lo importaría montándolo como volumen en el directorio `/docker-entrypoint-initdb.d/` de MariaDB (o ejecutándolo manualmente con `mysql < backup.sql`). Para Redis no haría falta migrar nada porque es solo caché y se regenera automáticamente.

---

### Sobre escalabilidad

**¿El sistema puede escalar horizontalmente?**

Sí. El servicio frontend se puede escalar a múltiples réplicas con `docker compose up -d --scale frontend=3`. Nginx reparte automáticamente las peticiones entre todas las réplicas gracias al DNS interno de Docker, que resuelve el nombre `frontend` a las IPs de todas las réplicas activas.

**¿Qué servicios son stateless y cuáles stateful?**

- **Stateless (sin estado):** El frontend y la API. No guardan ningún dato localmente; toda la información está en la base de datos o en Redis. Por eso se pueden escalar fácilmente: cualquier réplica puede atender cualquier petición.
- **Stateful (con estado):** MariaDB y Redis. Guardan datos que deben persistir. Escalar estos servicios es mucho más complicado porque habría que configurar replicación para mantener los datos sincronizados entre réplicas.

**¿Cómo manejarías mayor carga?**

Primero escalaría horizontalmente el frontend y la API (añadir más réplicas). Si el cuello de botella fuera la base de datos, añadiría réplicas de lectura de MariaDB. Si fuera la caché, podría pasar a un clúster de Redis. Y si el tráfico creciera mucho más, sería el momento de migrar a un orquestador como Kubernetes.

**¿Qué cuellos de botella identificas?**

El principal cuello de botella es la base de datos, porque es el único servicio que no se puede escalar fácilmente sin configurar replicación. Todas las réplicas del frontend y la API consultan la misma instancia de MariaDB. Redis ayuda a mitigar esto al cachear las consultas más frecuentes, pero si hay muchas escrituras (nuevos posts o comentarios), la caché se invalida y las consultas van directamente a la base de datos.

---

### Sobre monitoreo

**¿Cómo sabrías si un servicio falla?**

Gracias a los healthchecks configurados en `docker-compose.yml`. Docker ejecuta una prueba de vida dentro de cada contenedor periódicamente. Si el healthcheck falla varias veces seguidas, el contenedor se marca como `unhealthy` y se puede ver con `docker compose ps`. Además, el panel de monitoreo (`/monitor.php`) muestra el estado en tiempo real de MySQL, Redis y la API.

**¿Qué métricas son importantes monitorear?**

Las métricas más importantes que ya estamos recogiendo son:

- **Tiempo de respuesta medio:** si sube mucho, algo va lento.
- **Tasa de caché (hit rate):** si baja, significa que Redis no está siendo efectivo y la base de datos está recibiendo más carga de la que debería.
- **Número de peticiones:** para detectar picos de tráfico inusuales.
- **Estado de los servicios:** saber al instante si algún servicio se ha caído.
- **Uso de disco de los volúmenes:** para prevenir que la base de datos llene el disco.

**¿Cómo implementarías alertas?**

Implementaría un stack de monitoreo con **Prometheus** (para recoger métricas) y **Grafana** (para visualizarlas y configurar alertas). Prometheus rasparía las métricas de cada servicio periódicamente y Grafana enviaría notificaciones por correo electrónico o Slack cuando una métrica supere un umbral definido (por ejemplo, si el tiempo de respuesta medio supera los 500 ms o si un servicio lleva más de 1 minuto en estado unhealthy).

---

## Estructura del proyecto

```
ProyectoFinalDespliegue/
├── docker-compose.yml          # Orquestación de todos los servicios
├── nginx.conf                  # Configuración del proxy inverso
├── .env                        # Variables de entorno (NO se sube a Git)
├── .gitignore                  # Archivos excluidos del repositorio
│
├── frontend/                   # Servicio Frontend (PHP-FPM)
│   ├── Dockerfile              # Imagen optimizada con multi-stage build
│   ├── .dockerignore           # Archivos excluidos de la imagen
│   ├── public/                 # Archivos PHP accesibles
│   │   ├── index.php           # Página principal del blog
│   │   ├── post.php            # Vista detallada de un post
│   │   ├── monitor.php         # Panel de monitoreo
│   │   └── health.php          # Endpoint de healthcheck
│   ├── config/                 # Configuración de PHP
│   │   ├── database.php        # Conexión a MariaDB
│   │   ├── redis.php           # Conexión a Redis
│   │   └── metrics.php         # Sistema de métricas
│   └── php-fpm.d/
│       └── zz-docker.conf      # Configuración de PHP-FPM
│
├── api/                        # Servicio API REST (PHP-CLI)
│   ├── Dockerfile              # Imagen optimizada con multi-stage build
│   ├── public/
│   │   └── index.php           # Router de la API
│   └── config/                 # Configuración compartida
│       ├── database.php
│       ├── redis.php
│       └── metrics.php
│
└── database/
    └── init.sql                # Script de inicialización de la BD
```

---

## Licencia

Proyecto desarrollado con fines educativos para el módulo de Despliegue de Aplicaciones Web — 2º DAW.
