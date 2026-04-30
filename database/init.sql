-- Crear base de datos
CREATE DATABASE IF NOT EXISTS blogdb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE blogdb;

-- Tabla de usuarios
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla de posts
CREATE TABLE IF NOT EXISTS posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    views INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_created (created_at),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla de comentarios
CREATE TABLE IF NOT EXISTS comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_post (post_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insertar usuarios de ejemplo
INSERT INTO users (username, email, password) VALUES
('admin', 'admin@microblog.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('juan_dev', 'juan@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('maria_tech', 'maria@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('pedro_code', 'pedro@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Insertar posts de ejemplo
INSERT INTO posts (user_id, title, content) VALUES
(1, 'Bienvenido a MicroBlog', 'Este es un sistema de blog completo construido con Docker y arquitectura de microservicios. Incluye PHP, MySQL, Redis, Nginx y phpMyAdmin trabajando juntos en perfecta armonía. Esta es una demostración de cómo los contenedores Docker pueden simplificar el despliegue de aplicaciones complejas.'),
(2, 'Introducción a Docker', 'Docker ha revolucionado la forma en que desarrollamos y desplegamos aplicaciones. Con Docker, podemos empaquetar una aplicación y todas sus dependencias en un contenedor que puede ejecutarse en cualquier sistema que tenga Docker instalado. Esto elimina el famoso problema de "en mi máquina funciona".'),
(2, 'Arquitectura de Microservicios', 'La arquitectura de microservicios divide una aplicación en servicios pequeños e independientes que se comunican entre sí. Cada servicio puede desarrollarse, desplegarse y escalarse de forma independiente. Docker es perfecto para implementar microservicios ya que cada servicio puede vivir en su propio contenedor.'),
(3, 'Docker Compose: Orquestación Simplificada', 'Docker Compose nos permite definir y ejecutar aplicaciones multi-contenedor de forma sencilla. Con un simple archivo YAML podemos describir todos los servicios, redes y volúmenes que nuestra aplicación necesita. Un solo comando y toda la infraestructura está funcionando.'),
(3, 'Redis como Sistema de Caché', 'Redis es una base de datos en memoria extremadamente rápida, perfecta para implementar sistemas de caché. En este blog, usamos Redis para cachear las consultas más frecuentes a la base de datos, mejorando significativamente el rendimiento y reduciendo la carga en MySQL.'),
(4, 'Nginx como Proxy Inverso', 'Nginx es uno de los servidores web más eficientes y versátiles. En esta aplicación lo usamos como proxy inverso para enrutar las peticiones a los diferentes servicios. También podría usarse para balanceo de carga, SSL/TLS, compresión y caché de contenido estático.'),
(4, 'Persistencia de Datos en Docker', 'Los contenedores Docker son efímeros por naturaleza, pero nuestros datos deben persistir. Para esto usamos volúmenes Docker que almacenan los datos fuera del contenedor. En este proyecto, tanto la base de datos MySQL como los archivos subidos se almacenan en volúmenes persistentes.'),
(1, 'Mejores Prácticas con Docker', 'Al trabajar con Docker es importante seguir ciertas mejores prácticas: usar imágenes oficiales cuando sea posible, mantener las imágenes pequeñas, no ejecutar procesos como root, usar .dockerignore, etiquetar las imágenes apropiadamente y mantener los contenedores sin estado cuando sea posible.');

-- Insertar comentarios de ejemplo
INSERT INTO comments (post_id, user_id, content) VALUES
(1, 2, '¡Excelente proyecto! Me encanta cómo integra todas estas tecnologías.'),
(1, 3, 'Muy útil para aprender Docker. ¿Tienes el código en GitHub?'),
(2, 1, 'Gran explicación de Docker. Me ayudó mucho a entenderlo mejor.'),
(2, 4, 'Docker ha cambiado completamente mi flujo de trabajo de desarrollo.'),
(3, 1, 'Los microservicios son el futuro del desarrollo de software.'),
(4, 2, 'Docker Compose es increíblemente poderoso y fácil de usar.'),
(5, 3, 'Redis es asombroso. La diferencia de rendimiento es notable.'),
(6, 4, 'Nginx es mi servidor web favorito. Muy eficiente.'),
(7, 1, 'Los volúmenes Docker son esenciales para aplicaciones en producción.'),
(8, 2, '¡Gracias por compartir estas mejores prácticas!');

-- Mensaje de confirmación
SELECT 'Base de datos inicializada correctamente' AS mensaje;
SELECT COUNT(*) as total_users FROM users;
SELECT COUNT(*) as total_posts FROM posts;
SELECT COUNT(*) as total_comments FROM comments;