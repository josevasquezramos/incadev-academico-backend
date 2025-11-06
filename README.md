# Procesos Académicos Incadev

Este repositorio contiene el backend de la aplicación que gestiona los procesos académicos de [Incadev](https://github.com/incadev-uns). Este proyecto depende del paquete [incadev-uns/core-domain](https://github.com/incadev-uns/core-domain). Las migraciones, modelos y el seeder principal están en ese paquete.

## ⚙️ Requisitos

- PHP ^8.2
- Composer
- MySQL / PostgreSQL u otra BD (activar su driver o extenión)
- ImageMagick (requerido para la generación de QR en certificados)

## 🚀 Instalación

### 1. Clonar el repositorio

```bash
git clone https://github.com/josevasquezramos/incadev-academico-backend.git
cd incadev-academico-backend
```

### 2. Instalar dependencias

```bash
composer install
```

### 3. Copiar archivo de entorno

```bash
cp .env.example .env
```

### 4. Generar APP_KEY

```bash
php artisan key:generate
```

### 5. Configurar variables de entorno

Edita .env y configura las variables de entorno necesarias.

### 6. Ejecutar migraciones

```bash
php artisan migrate
```

### 7. Ejecutar seeders del paquete

```bash
php artisan db:seed --class="IncadevUns\CoreDomain\Database\Seeders\IncadevSeeder"
```

### 8. Levantar servidor

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

## 📘 Documentación de endpoints

### 1. Matrículas

Este módulo gestiona el proceso de inscripción de alumnos en grupos disponibles. Permite listar los grupos abiertos a matrícula y registrar una nueva matrícula junto con los datos del pago realizado.

#### 1.1. Listar grupos disponibles

Obtiene todos los grupos disponibles para matrícula.

> **GET** `/api/available-groups`

#### 1.2. Matricularse en un grupo

Registra la matrícula de un usuario en un grupo y guarda el pago.

> **POST** `/api/available-groups/{group}/enroll`

Body (JSON):

```json
{
    "operation_number": "OP-123456789",
    "agency_number": "AG-001",
    "operation_date": "2024-01-15",
    "amount": 500.00,
    "evidence_path": "recibo.jpg"
}
```

### 2. Alumno

El módulo del alumno agrupa todas las funcionalidades relacionadas con la experiencia del estudiante dentro de la plataforma. Permite consultar los grupos en los que el alumno está matriculado, revisar los detalles de cada grupo (módulos, clases, materiales, exámenes, asistencias y notas).

#### 2.1. Grupos matriculados

Devuelve todos los grupos en los que el usuario está matriculado.

> **GET** `/api/enrolled-groups`

#### 2.2. Detalle de un grupo

Muestra información completa del grupo: módulos, clases, materiales, exámenes, asistencias y notas del alumno.

> **GET** `/api/enrolled-groups/{group}`

### 3. Profesor

Este módulo permite a los docentes gestionar los grupos que enseñan y todas las entidades asociadas: clases, materiales, exámenes y asistencias. Provee herramientas para crear y editar clases, cargar materiales, planificar y calificar exámenes, registrar asistencias y completar grupos cuando cumplan los requisitos académicos.

#### 3.1. Gestión de grupos

##### Mis grupos

> **GET** `/api/teaching-groups`

Devuelve todos los grupos en los que el usuario es profesor.

##### Detalle de un grupo

> **GET** `/api/teaching-groups/{group}`

Muestra toda la información del grupo (módulos, clases, exámenes, etc.).

##### Verificar si un grupo puede completarse

> **GET** `/api/teaching-groups/{group}/can-complete`

Devuelve si el grupo cumple las condiciones para ser completado (todas las clases y exámenes calificados, asistencias y notas registradas).

##### Completar un grupo

> **POST** `/api/teaching-groups/{group}/complete`

Finaliza un grupo, genera notas finales y certificados.

#### 3.2. Gestión de clases

##### Obtener clases

> **GET** `/api/teaching-groups/{group}/classes`

Lista todas las clases del grupo.

##### Registrar clase

> **POST** `/api/teaching-groups/{group}/modules/{module}/classes`

Crea una clase nueva para un determinado módulo.

```json
{
  "title": "Clase introductoria",
  "start_time": "2025-11-15 10:00:00",
  "end_time": "2025-11-15 12:00:00",
  "meet_url": "https://meet.google.com/abc-def-ghi"
}
```

##### Editar clase

> **PUT** `/api/teaching-groups/classes/{class}`

Actualiza una clase. El body es el mismo que el de registrar.

##### Eliminar clase

> **DELETE** `/api/teaching-groups/classes/{class}`

Elimina una clase (solo el profesor autorizado puede hacerlo).

#### 3.3. Gestión de materiales

##### Obtener materiales

> **GET** `/api/teaching-groups/classes/{class}/materials`

Lista materiales de una clase.

##### Registrar material

> **POST** `/api/teaching-groups/classes/{class}/materials`

Crea un material.

```json
{
  "type": "video",
  "material_url": "https://youtube.com/watch?v=abc123"
}
```

##### Editar material

> **PUT** `/api/teaching-groups/materials/{material}`

Actualiza un material.

##### Eliminar material

> **DELETE** `/api/teaching-groups/materials/{material}`

Elimina un material.

#### 3.4. Gestión de exámenes

##### Obtener exámenes

> **GET** `/api/teaching-groups/{group}/exams`

Lista exámenes de un grupo.

##### Registrar examen

> **POST** `/api/teaching-groups/{group}/modules/{module}/exams`

Crea un examen para un determinado módulo.

```json
{
  "title": "Examen final",
  "start_time": "2025-12-15 10:00:00",
  "end_time": "2025-12-15 12:00:00",
  "exam_url": "https://google.forms.com/exams"
}
```

##### Obtener examen en específico

> **GET** `/api/teaching-groups/exams/{exam}`

Muestra información necesaria para el siguiente endpoint (enrollment_id).

##### Registro masivo

> **POST** `/api/teaching-groups/exams/{exam}/grades`

Registro masivo de notas. Se puede usar este mismo método y body para actualizar masivamente.

```json
{
  "grades": [
    { "enrollment_id": 1, "grade": 16.5, "feedback": "Buen trabajo" },
    { "enrollment_id": 2, "grade": 8.0, "feedback": "Debe mejorar" }
  ]
}
```

##### Editar nota

> **PUT** `/api/teaching-groups/grades/{grade}`

De ser necesario edita una nota individual.

```json
{
    "grade": 17.0,
    "feedback": "Nota corregida"
}
```

##### Eliminar examen

> **DELETE** `/api/teaching-groups/exams/{exam}`

Elimina un examen.

#### 3.5. Gestión de asistencias

##### Obtener asistencias

> **GET** `/api/teaching-groups/{group}/attendances`

Lista todas las clases de un grupo.

##### Obtener listado específico

> **GET** `/api/teaching-groups/classes/{class}/attendances`

Muestra los alumnos de una clase con sus estados de asistencia.

##### Registro masivo

> **POST** `/api/teaching-groups/classes/{class}/attendances`

Registro masivo de asistencias.

```json
{
  "attendances": [
    { "enrollment_id": 1, "status": "present" },
    { "enrollment_id": 2, "status": "late" },
    { "enrollment_id": 3, "status": "absent" },
    { "enrollment_id": 4, "status": "excused" },
  ]
}
```

##### Editar asistencia

> **PUT** `/api/teaching-groups/attendances/{attendance}`

Edita asistencia individual.

```json
{
    "status": "present"
}
```

##### Estadísticas

> **GET** `/api/teaching-groups/{group}/attendance-statistics`

Devuelve estadísticas de asistencia por grupo.

### 4. Certificados

El módulo de certificados gestiona la emisión y descarga de certificados digitales generados al completar satisfactoriamente un grupo o curso. Estos certificados se generan automáticamente al completar un grupo desde el módulo del profesor y están disponibles para el alumno en formato PDF.

#### Obtener grupos finalizados

> **GET** `/api/student/completed-groups`

Lista de grupos finalizados con enlaces a certificados.

#### Descargar certificado

> **GET** `/api/student/certificates/{uuid}/download`

Descarga el certificado en formato PDF.
