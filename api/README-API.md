# MicroBlog - API REST

## Base URL

- `http://localhost/api`

## Endpoints

### GET /api/posts

Lista todos los posts con metadatos y contador de comentarios.

**Respuesta 200**

```json
[
  {
    "id": 1,
    "user_id": 1,
    "title": "Bienvenido a MicroBlog",
    "content": "...",
    "views": 10,
    "created_at": "2026-04-30 14:32:10",
    "updated_at": "2026-04-30 14:32:10",
    "username": "admin",
    "comment_count": 2
  }
]
```

### GET /api/posts/:id

Obtiene un post especifico y sus comentarios.

**Respuesta 200**

```json
{
  "post": {
    "id": 1,
    "user_id": 1,
    "title": "Bienvenido a MicroBlog",
    "content": "...",
    "views": 10,
    "created_at": "2026-04-30 14:32:10",
    "updated_at": "2026-04-30 14:32:10",
    "username": "admin"
  },
  "comments": [
    {
      "id": 1,
      "post_id": 1,
      "user_id": 2,
      "content": "Excelente proyecto",
      "created_at": "2026-04-30 14:33:00",
      "username": "juan_dev"
    }
  ]
}
```

**Respuesta 404**

```json
{
  "error": "Post no encontrado"
}
```

### POST /api/posts

Crea un nuevo post.

**Body (JSON)**

```json
{
  "user_id": 1,
  "title": "Nuevo post",
  "content": "Contenido del post"
}
```

**Respuesta 201**

```json
{
  "id": 9,
  "status": "created"
}
```

**Respuesta 422**

```json
{
  "error": "Datos invalidos. Se requiere user_id, title y content."
}
```

### GET /api/stats

Estadisticas del sistema.

**Respuesta 200**

```json
{
  "posts": 8,
  "users": 4,
  "comments": 10,
  "views": 125,
  "requests": 50,
  "avg_response_ms": 12.45,
  "cache_hits": 10,
  "cache_misses": 5,
  "services": {
    "db": "ok",
    "redis": "ok"
  },
  "timestamp": "2026-04-30T14:40:10+00:00"
}
```
