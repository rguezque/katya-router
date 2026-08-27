# Katya

A lightweight PHP router

**Tabla de contenidos**

- [Install](#install)
- [Configuration]("configuration")
    - [Autoloader](#autoloader)
- [Routing](#routing)
    - [Shortcuts](#shortcuts)
    - [Controllers](#controllers)
- [Routes group](#routes-group)
- [Wildcards](#wildcards)
- [Views](#views)
- [Request](#request)
- [Response](#response)
    - [HttpHeaders](#httpheaders)
- [Stream](#stream)

- [SapiEmitter](#sapiemitter)
- [Session](#session)
- [Services](#services)
- [DB Connection](#db-connection)
    - [Connecting using a Database URL](#connecting-using-a-database-url)
    - [Using a unix socket](#using-a-unix-socket)
    - [Auto connect](#auto-connect)
    - [Create new instances](#create-new-instances)
    - [Transactions](#transactions)
- [Middlewares](#middlewares)
- [CORS](#cors)
- [Error Handler](#error-handler)
- [helpers\*](#helpers)

## Install

Desde la terminal en la raíz del proyecto:

```bash
composer require rguezque/katya-router
```

## Configuration

Para servidor **Apache**, en el directorio del proyecto crea y edita un archivo `.htaccess` con lo siguiente:

```htaccess
<IfModule mod_rewrite.c>
    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Handle Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
```

Para **Nginx** edita el archivo de configuración de la siguiente forma:

```
server {
    location / {
        try_files $uri $uri/ /index.php;
    }
}
```

Para prueba desde el servidor _inbuilt_ de PHP, dentro del directorio del proyecto ejecuta en la terminal:

```bash
php -S localhost:80
```

Y abre en el navegador web la dirección `http://localhost:80`

### Autoloader

Desde la terminal, ubícate dentro del directorio del proyecto y ejecuta:

```bash
composer dump-autoload -o
```

## Routing

Cada ruta se define con el método `Katya::route`, que recibe 3 argumentos, el método de petición (solo son soportados `GET`, `POST`, `PUT`, `PATCH` y `DELETE`), la ruta y el controlador a ejecutar para dicha ruta. Los controladores siempre reciben un objeto `Request` que contiene los métodos necesarios para manejar una petición (Ver [Request](#request)) y deben devolver un `Response` (Ver [Response](#response)).

Para iniciar el router se invoca el método `Katya::run` y se le envía un objeto `Request`.

```php
require __DIR__.'/vendor/autoload.php';

use rguezque\{
    HtmlResponse,
    HttpStatus,
    Katya,
    Request,
    Response
};
use rguezque\Exception\{
    RouteNotFoundException,
    UnsupportedRequestMethodException
};
use UnexpectedValueException;

$router = new Katya;

$router->route(Katya::GET, '/', function(Request $request) {
    return new Response('hola mundo!');
});

try {
    $response = $router->run(Request::fromGlobals());
} catch(RouteNotFoundException $e) {
    $message = sprintf('<h1>Not Found</h1><p>%s</p>', $e->getMessage());
    $response = new Response($message, HttpStatus::HTTP_NOT_FOUND);
} catch(UnsupportedRequestMethodException $e) {
    $message = sprintf('<h1>Not Allowed</h1><p>%s</p>', $e->getMessage());
    $response = $new Response($message, HttpStatus::HTTP_METHOD_NOT_ALLOWED);
} catch(UnexpectedValueException $e) {
    $message = sprintf('<h1>Not Allowed</h1><p>%s</p>', $e->getMessage());
    $response = $new Response($message, HttpStatus::HTTP_METHOD_NOT_ALLOWED);
}

SapiEmitter::emit($response);
```

El router acepta un prefijo global, este se aplica a cada una de las rutas que se definan.

```php
$katya = new Katya('/nombre_directorio_base');
```

El router devuelve tres posibles excepciones, `RouteNotFoundException` cuando no se encuentra una ruta, `UnsupportedRequestMethodException` cuando un método de petición no está soportado por el router o un `UnexpectedValueException` cuando el controlador no devuelve un tipo `Response`. Utiliza un `try-catch` para atraparlas y manejar el `Response` apropiado como se ve en el ejemplo anterior.

**Detener el router**

En cualquier momento se puede detener el router, ya sea desde un middleware o desde un controlador. Para esto utiliza el método estático `Katya::halt` el cual recibe como argumento un objeto `Response`. Este método interrumpe el flujo de procesos del router lanzando una excepción `HaltException`. En el bloque `try-catch` atrapa esta excepción y recupera el `Response` para enviarlo al cliente.

```php
require __DIR__.'/vendor/autoload.php';

use rguezque\{
    HtmlResponse,
    HttpStatus,
    Katya,
    Request,
    Response,
    SapiEmitter,
};
use rguezque\Exception\{
    RouteNotFoundException,
    UnsupportedRequestMethodException
};
use UnexpectedValueException;

$router = new Katya;

$router->route(Katya::GET, '/', function(Request $request) {
    $response = new Response('Router detenido');
    Katya::halt($response);
});

try {
    $response = $router->run(Request::fromGlobals());
} catch(RouteNotFoundException $e) {
    $message = sprintf('<h1>Not Found</h1><p>%s</p>', $e->getMessage());
    $response = new HtmlResponse($message, HttpStatus::HTTP_NOT_FOUND);
} catch(UnsupportedRequestMethodException $e) {
    $message = sprintf('<h1>Not Allowed</h1><p>%s</p>', $e->getMessage());
    $response = new HtmlResponse($message, HttpStatus::HTTP_METHOD_NOT_ALLOWED);
} catch(UnexpectedValueException $e) {
    $message = sprintf('<h1>Invalid Argument</h1><p>%s</p>', $e->getMessage());
    $response = new HtmlResponse($message, HttpStatus::HTTP_UNSUPPORTED_MEDIA_TYPE);
} catch(HaltException $e) {
    $response = $e->getResponse();
}

SapiEmitter::emit($response);
```

### Shortcuts

Los atajos `Katya::get`, `Katya::post`, `Katya::put`, `Katya::patch` y `Katya::delete` sirven respectivamente para agregar rutas de tipo `GET`, `POST`, `PUT`, `PATCH` y `DELETE` al router.

```php
$katya = new Katya;
$katya->get('/', function(Request $request) {
    return new Response('Hello')
});

$katya->post('/', function(Request $request) {
    $data = [
        'name' => 'John',
        'lastname' => 'Doe'
    ];

    return new JsonResponse($data);
});
```

### Controllers

Los controladores pueden ser: una función anónima, un método estático o un método de un objeto.

```php
// Usando una función anónima
$katya->get('/user', function(Request $request) {
    //...
});

// Usando un método estático
$katya->get('/user', ['App\Controller\GreetingController', 'showProfileAction']);
// o bien
use App\Controller\UserController;
$katya->get('/user', [UserController::class, 'showProfileAction']);
$katya->get('/user/permissions', [UserController::class, 'showPermissionsAction']);

// Usando un método de un objeto
$user = new App\Controller\UserController();
$katya->get('/user', [$user, 'showProfileAction']);
```

> [!TIP]
> Nombra las clases con el sufijo `Controller` y los métodos con el sufijo `Action` para identificarlos mejor.

## Routes group

Para crear grupos de rutas bajo un mismo prefijo se utiliza `Katya::group`; recibe 2 argumentos, el prefijo de ruta y una función anónima que recibe un objeto `Group` con el cual se definen las rutas del grupo.

```php
// Se generan las rutas "/foo/bar" y "/foo/baz"
$katya->group('/foo', function(Group $group) {
    $group->get('/bar', function(Request $request) {
        return new Response(' Hello foobar');
    });

    $group->get('/baz', function(Request $request, Services $service) {
        // Registrado previamente como un servicio
        $template = $services->view->fetch('welcome')
        return new HtmlResponse($template);
    });
});
```

## Wildcards

Los _wildcards_ son parámetros definidos en la ruta. El router busca las coincidencias de acuerdo a la petición y los envía como argumentos al controlador de ruta a través del objeto `Request`, estos argumentos son recuperados con el método `Request::getParams` que devuelve por default un objeto `Parameters` donde cada clave se corresponde con el mismo nombre de los _wildcards_. El argumento por default de esté método es `Request::PARAMS_ASSOC` el cual indica que el _array_ de parámetros tiene índices nombrados correspondientes a los _wildcards_ y no numéricos.

Los _wildcards_ permiten la definición de `regex` opcionales para hacer más estrictas las rutas.

```php
// Si `edad` no es un número entero, arrojará una excepción `RouteNotFoundException`
$katya->get('/hola/{nombre}/{edad: \d+}', function(Request $request) {
    // Filtra los parámetros y devuelve un objeto Parameter que encapsula un array asociativo (clave-valor)
    // Usa $request->getParams(Request::PARAMS_BOTH) para devolver el array de párametros sin filtrar
    $params = $request->getParams();
    return new JsonResponse(['nombre' => $params['nombre'], 'edad' => $params['edad']]);
});
```

El objeto `Parameters` tiene los siguientes métodos:

- `get(string $key, mixed $default = null)`: Devuelve un parámetro por nombre o el valor default especificado, si no existe.
- `set(string $key, mixed $value)`: Agrega o sobrescribe un parámetro.
- `all()`: Devuelve todo el array de parámetros.
- `has(string $key)`: Devuelve `true` si un parámetro existe, `false` en caso contrario.
- `valid(string $key)`: Devuelve `true` si un parámetro existe y si no es `null` y no está vacío; `false` en caso contrario.
- `remove(string $key)`: Elimina un parámetro por nombre.
- `clear()`: Elimina todos los parámetros.
- `keys()`: Devuelve una lista con los nombres de todos los parámetros.
- `gettype(string $key)`: Devuelve el tipo de dato o nombre de objeto de un parámetro.

Si los _wildcards_ fueron definidos como expresiones regulares puras, envía el argumento `Request::PARAMS_NUM` el cual devuelve un _array_ lineal con los valores de las coincidencias encontradas.

```php
$katya->get('/hola/(\w+)/(\w+)/(\d+)', function(Request $request) {
    $params = $request->getParams(Request::PARAMS_NUM); // Devuelve un array lineal
    list($nombre, $apellido, $edad) = $params;
    return new Response(sprintf('Hola %s %s, tu edad recibida es: %d', $nombre, $apellido, $edad));
});
```

> [!IMPORTANT]
> Evita mezclar parámetros nombrados y expresiones regulares en la misma definición de una ruta, pues no podrás recuperar por nombre los que hayan sido definidos como _regex_. En todo caso si esto sucede, envía el argumento `Request::PARAMS_BOTH` para recuperar un **_array_** con todos los parámetros en el orden que hayan sido definidos en la ruta.

## Views

Las vistas son el medio por el cual el router devuelve y renderiza un objeto `HtmlResponse` con contenido HTML en el navegador. La única configuración que se necesita es definir el directorio donde estarán alojadas las plantillas.

```php
use rguezque\View;

// Standalone
$view = new ViewEngine(
    __DIR__.'/views/templates', // Directorio donde se alojan los templates
);

// Enviandolo como un servicio
$services = new Services();
$services->register('view', function() {
    return new ViewEngine(
        __DIR__.'/views/templates'
    );
});
$router->setServices($services);
```

El método `ViewEngine::fetch` recibe el nombre de la plantilla (puede omitirse el sufijo del archivo) y opcionalmente un array con variables. Devuelve en un string lo contenidos de la plantilla, listo para ser enviado como un `HtmlResponse`. Los archivos deben nombrarse con el sufijo y extensión `*.view.php`.

```php
$router->get('/home', function(Request $request, Services $service): Response {
    $view = $service->view();
    $data = [
    	'home' => '/',
    	'about' => '/about-us',
    	'contact' => '/contact-us'
	];
	$template = $view->fetch('menu', $data);
    return new HtmlResponse($template);
});
```

Recibe los parámetros enviados en `$data` (según el ejemplo del bloque de código de arriba)

```php
//menu.view.php
<nav>
    <ul>
        <li><a href="<?= $home ?>">Home</a></li>
        <li><a href="<?= $about ?>">About</a></li>
        <li><a href="<?= $contact ?>">Contact</a></li>
    </ul>
</nav>
```

También es posible agregar fragmentos de vistas, como argumentos para extender una vista principal, con el método `ViewEngine::fetchFragment`, que recibe tres argumentos: el nombre de la plantilla, el nombre con el que se inyectará a la plantilla principal y un array opcional de argumentos para la actual plantilla.

```php
$router->get('/home', function(Request $request, Services $service): Response {
    $view = $service->view();
    // Recibe el
	$fragment = $view->fetchFragment('top_menu', 'menu_superior');
    $template = $view->fetch('home');
    return new HtmlResponse($template);
});
```

Imprime en pantalla el contenido de `top_menu.php` guardado previamente con el alias `'menu_superior'`.

```php
// index.view.php
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documento</title>
</head>
<body>
    <?= $menu_superior ?>
</body>
</html>
```

O bien, directamente desde una plantilla con `ViewEngine::insert`:

```php
// index.view.php
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documento</title>
</head>
<body>
    <?= $this->insert('sidebar.view.php'); ?>
</body>
</html>
```

Otros métodos disponibles son:

- `addArgument(string $key, mixed $value)`: Agrega un argumento por nombre a la vez.
- `addArguments(array $data)`: Agrega un array asociativo de argumentos de tipo clave-valor a los ya existentes.
- `setArguments(array $data)`: Asigna o sobrescribe los argumentos para la plantilla.
- `e(?string $string)`: Dentro de una plantilla, escapa una cadena de texto para una salida segura en HTML.
- `asset(string $path)`: Dentro de una plantilla, genera una URL absoluta o relativa para un recurso (CSS, JS, imágenes) agregando una marca de tiempo para invalidar la caché del navegador _(Cache Busting)_.

> [!IMPORTANT]
> El método `ViewEngine::fetch` es el último que se debe invocar. Cualquier otro método que se invoque después de este, no tendrá efecto.

## Request

Los métodos de la clase `Request` que empiezan con `get` devuelven un objeto `Parameters` con excepción de `Request::getParams` que depende del filtro que se le especifique.

- `fromGlobals()`: Crea un objeto `Request` con las variables globales PHP.
- `getQuery()`: Devuelve el array de parámetros `$_GET`.
- `getParsedBody()`: Devuelve el array de parámetros `$_POST`.
- `getBody()`: Devuelve el _stream_ `php://input` encapsulado en un objeto `Stream`.
- `getServer()`: Devuelve el array de parámetros `$_SERVER`.
- `getCookies()`: Devuelve el array de parámetros `$_COOKIE`.
- `getFiles()`: Devuelve el array de parámetros `$_FILES`.
- `getParams(Request::PARAMS_ASSOC)`: Devuelve el array de parámetros nombrados de una ruta solicitada. Dependiendo de la definición de los _wildcards_ de una ruta, se puede especificar el formato de datos a devolver (Ver [Wildcards](#wildcards)).
- `getAllHeaders()`: Devuelve todos los encabezados HTTP recibidos en la actual petición.
- `getHeaderLine(string $name, ?string $default = null)`: Devuelve el contenido de una cabecera HTTP específica.
- `getUri()`: Devuelve un objeto `Uri` que representa la URL de la petición actual.
- `withAddedHeader(string $name, string $value)`: Devuelve un objeto `Request` clonado con la cabecera especificada añadida.
- `withQuery(array $query)`: Devuelve un objeto `Request` clonado con los nuevos valores `$_GET`.
- `withBody(array $body)`: Devuelve un objeto `Request` clonado con los nuevos valores `$_POST`.
- `withServer(array $server)`: Devuelve un objeto `Request` clonado con los nuevos valores `$_SERVER`.
- `withCookies(array $cookies)`: Devuelve un objeto `Request` clonado con los nuevos valores `$_COOKIE`.
- `withFiles(array $files)`: Devuelve un objeto `Request` clonado con los nuevos valores `$_FILES`.
- `withParams(array $params)`: Devuelve un objeto `Request` clonado con los nuevos valores de los parámetros con nombre.
- `withAddedParams(array $params)`: Devuelve un objeto `Request` clonado con parámetros añadidos a los parámetros con nombre existentes.
- `buildQuery(string $uri, array $params)`: Genera y devuelve una cadena de petición `GET` en una URI.

## Response

Métodos de la clase `Response`.

- `clear()`: Limpia los valores del `Response`.
- `setStatusCode(int $code)`: Asigna un código númerico de estatus HTTP.
- `getStatusCode()`: Devuelve el actual código de estatus HTTP.
- `headers`: Atributo público de tipo `HttpHeaders`. Contiene métodos para agregar encabezados HTTP al `Response`.
- `body`: Atributo público de tipo `Stream`. Contiene métodos para agregar contenido al cuerpo del `Response`.

```php
$response = new \rguezque\Response();
$response->headers->set('Content-Type', 'text/html');
$response->body->write('Not Found. The request URL do not match any route.');
$response->setStatusCode(\rguezque\HttpStatus::HTTP_NOT_FOUND);
```

> [!TIP]
> Utiliza `JsonResponse` para devolver datos de una API en formato `JSON` , `HtmlResponse` para devolver contenido `html` (vistas) y `RedirectResponse` para redirecciones.

### HttpHeaders

Métodos de la clase:

- `set(string $key, string $value)`: Agrega un encabezado HTTP.
- `get(string $key, ?string $default = null)`: Devuelve un encabezado por nombre.
- `remove(string $key)`: Elimina un encabezado por nombre.
- `clear()`: Elimina todos los encabezados.
- `all()`: Devuelve todos los encabezados en un _array_.
- `has(string $key)`: Devuelve `true` si un encabezado existe, `false` en caso contrario.

## Stream

Su función es proporcionar un contenedor orientado a objetos para manipular los recursos de flujos (streams) en PHP, abstrayendo operaciones de lectura, escritura y posicionamiento de datos como archivos, memoria o entradas de red (HTTP).

Dependiendo de si lo usas en una Request o una Response, la funcionalidad varía:

1. **En un Request (Petición)**

    Sirve para leer los datos enviados por el cliente (ej. payloads JSON de una API). Encapsula el _stream_ `php://input`.
    - `$request->getBody()->getContents()`: Devuelve todo el contenido del cuerpo en un string.
    - `$request->getBody()->rewind()`: Vuelve al inicio del flujo si necesitas leerlo varias veces.

2. **En un Response (Respuesta)**

    Sirve para construir el contenido que enviarás de vuelta al cliente. Encapsula el _stream_ `php://memory`.
    - `$response->body->write("contenido")`: Escribe texto o datos en la respuesta. Múltiples llamadas concatenan el texto.

Los métodos de esta clase son:

- `write(mixed $string)`: Escribe contenido y retorna el total de bytes escritos; o `false` en error.
- `read(int $length)`: Lee el _stream_.
- `getContents()`: Recupera el contenido _string_ restante del _stream_ desde la posición actual del puntero.
- `detach()`: Libera y devuelve el flujo actual.
- `getSize()`: Devuelve el tamaño en bytes del _stream_.
- `tell()`: Devuelve la posición actual del puntero de lectura/escritura del _stream_.
- `eof()`: Devuelve `true` si el puntero del _stream_ está al final del archivo; `false` en caso contrario.
- `seek(int $offset, int $whence = SEEK_SET)`: Establece el indicador de posición del archivo referenciado por el _stream_. La nueva posición, medida en bytes desde el principio del archivo, se obtiene sumando el desplazamiento a la posición especificada por consiguiente.
- `rewind()`: Rebobina el puntero del _stream_ al inicio.
- `close()`: Cierra el flujo contra escritura.

## SapiEmitter

Emite el `Response` con el método estático `SapiEmitter::emit`.

```php
$response = $router->run(Request::fromGlobals());
SapiEmitter::emit($response);
```

Para más control utiliza `try-catch`:

```php
try {
    $response = $app->run(Request::fromGlobals());
} catch(UnexpectedValueException $e) {
    $response = new Response('el controlador retorno un resultado no válido', HttpStatus::HTTP_EXPECTATION_FAILED);
} catch(UnsupportedRequestMethodException $e) {
    $response = new Response('Método de petición no permitido', HttpStatus::HTTP_METHOD_NOT_ALLOWED);
} catch(RouteNotFoundException $e) {
    $response = new Response('Ruta no encontrada', HttpStatus::HTTP_NOT_FOUND);
}

SapiEmitter::emit($response);
```

## Session

La clase `Session` sirve para la creación de sesiones y la administración de variables de `$_SESSION` que son almacenadas en un _namespace_. Se inicializa o selecciona una colección de variables de sesión con el método estático `Session::withNamespace` el cual devuelve un objeto `Session`. Los métodos disponibles son:

- `withNamespace(string $namespace = '')`: Es el punto de entrada para crear o recuperar un objeto `Session` con el patrón _Singleton_. Se envía como argumento un nombre para el _namespace_ de las variables de sesión. Este método estático funciona como un constructor semántico para hacerlo más descriptivo; además de que implementa el patrón _Multiton_ que permita crear y administrar diferentes _namespace_ evitando sobrescribirlos.
- `exists(string $namespace)`: Método estático que devuelve `true` si un namespace ya existe, `false` en caso contrario.
- `start()`: Inicia o retoma la sesión activa.
- `started()`: Devuelve `true` si la sesión está activa.
- `regenerateId(bool $delete_old_session = true)`: Regenera el ID de la sesión actual.
- `getNamespace`: Devuelve el nombre del actual _namespace_.
- `set(string $key, mixed $value)`: Crea o sobrescribe una variable de sesion.
- `get(string $key, mixed $default = null)`: Devuelve una variable de sesión, si no existe devuelve el valor default que se asigne en el segundo parámetro.
- `all()`: Devuelve un array con todas las variables de sesión del actual _namespace_.
- `has(string $key)`: Devuelve `true` si existe una variable de sesión.
- `valid(string $key)`: Devuelve `true` si una variable de sesión no es `null` y no está vacía.
- `remove(string $key)`: Elimina una variable de sesión.
- `clear()`: Elimina todas las variables de sesión.
- `destroy()`; Destruye la sesión actual junto con las cookies y variables de sesión.

```php
$session = Session::withNamespace('my_custom_namespace');
$session->set('nombre', 'Juan');
$session->set('edad', 30);
$session->get('nombre');
```

## Services

La clase `Services` sirve para registrar servicios que se utilizarán en todo el proyecto. Con el método `Services::register` agregamos un servicio, este recibe 2 parámetros, un nombre y una función anónima. Para quitar un servicio `Services::unregister` recibe el nombre del servicio (o servicios, separados por coma) a eliminar.

Para asignarlos al router se envía el objeto `Services` a través del método `Katya::setServices`, a partir de aquí, cada controlador recibirá como tercer argumento la instancia de `Services`. Un servicio es invocado como si fuera un método más de la clase o bien como si fuera un atributo en contexto de objeto.

Opcionalmente se puede filtrar que servicios en específico serán utilizados en una ruta o grupo de rutas con `Route::useServices` y `Group::useServices` respectivamente. Este método recibe los nombres de los servicios, separados por comas.

Si se especifican servicios para un grupo, estos se heredan a sus rutas, excepto en aquellas rutas que tengan explicitamente definidos los servicios que utilizarán.

Para verificar si un servicio existe se usa `Services::has` (se envía como argumento el nombre del servicio) y `Services::names` devuelve un array con los nombres de todos los servicios disponibles.

```php
require __DIR__.'/vendor/autoload.php';

use rguezque\{Group, Katya, Request, HtmlResponse, Services, ViewEngine};

$router = new Katya;
$services = new Services;

// Ejemplo de registro eficiente de un servicio
$services->register('view', static function() {
    static $view = null;
    if ($view === null) {
        $view = new ViewEngine(
            templates_dir: __DIR__.'/views',
            cache_dir: __DIR__.'/views/cache'
        );
    }
    return $view;
});
$services->register('is_pair', function(int $number) {
    return $number % 2 == 0;
});

$router->setServices($services);

$router->get('/', function(Request $request, Services $service) {
    $view = $service->view(); // o bien en contexto de objeto: $service->view
    return new HtmlResponse($view->fetch('home.php'));
})->useServices('view'); // Solamente recibirá el servicio 'view'
```

## DB Connection

La clase `Connection` proporciona el medio para crear conexiones a MySQL a través del driver `PDO` o la clase `mysqli`. El método estático `Connection::create` funciona como un _factory_, y crea nuevas instancias de conexión devolviendo un objeto `ConnectionInterface`. Los valores posibles para el `driver` de conexión son: `pdomysql` o `mysqli`.

```php
use rguezque\Database\Connection;

$db = Connection::create([
    'driver' => 'pdomysql',
    'host' => 'localhost',
    'port' => 3306,
    'user' => 'root',
    'pass' => 'mypassword',
    'dbname' => 'mydatabase'
    'charset' => 'utf8mb4'
]);
```

> [!NOTE]
> Para el caso de conexiones con `PDO`, si se utiliza un _unix socket_ define el parámetro `unix_socket` que por lo regular en Linux es `/var/run/mysqld/mysqld.sock` o en el caso de XAMPP es `/opt/lampp/var/mysql/mysql.sock`; los parámetros `host` y `port` serán ignorados aunque hayan sido definidos.
>
> Para conexiones con `mysqli` si se define el parámetro `unix_socket` el valor de `host` debe ser `localhost`.

### Connecting using a Database URL

Otra alternativa es usar una _database URL_ como parámetro de conexión, a través del método `DsnParser::parse`; este recibe una URL y la procesa para ser enviada a `Connection::create` de la siguiente forma:

```php
use rguezque\Database\Connection;

// Con mysqli
// 'mysqli://root:mypassword@127.0.0.1/mydatabase?charset=utf8'
// Con PDO
$params = (new DsnParser)->parse('pdomysql://root:mypassword@127.0.0.1:3456/mydatabase?charset=utf8');
$db = Connection::create($params);
```

Esto devolverá:

```php
[
    'driver' => 'pdomysql',
    'user' => 'root',
    'password' => 'mypassword',
    'host' => '127.0.0.1',
    'port' => 3456, // Valor default 3306 si no se especifica en la URL
    'db_name' => 'mydatabase',
    'charset' => 'utf8' // Valor default utf8mb4 si no se especifica
]
```

### Using a unix socket

Si se define un _unix socket_ se le da prioridad en la conexión. Para conectarse especificando **parámetros** de conexión se hace de la siguiente forma:

```php
$params = [
    'driver' => 'pdomysql',
    'user' => 'root',
    'password' => 'mypassword',
    'db_name' => 'mydatabase',
    'unix_socket' => '/var/run/mysqld/mysqld.sock',
    'charset' => 'utf8'
];

$db = Connection::create($params);
```

Los parámetros `host` y `port` pueden ser omitidos ya que al normalizarse antes de la conexión son asignados por default como `localhost` y `3306` por default.

Para el caso de `PDO` estos son ignorados completamente aunque se definan en los parámetros o variables de entorno ya que se le da prioridad al parámetro `"unix_socket"`. En el caso de `mysqli` que si necesita especificar el _host_ como `localhost` funciona bien dejar que se asignen por default.

Utilizando una URL, el puerto puede ser omitido. Pero recordando el caso especial de `mysqli`, se debe especificar estrictamente el _host_ como `localhost`, además de que no puede omitirse o lanzará un `InvalidArgumentException` por la URL mal formada:

```php
$url = getenv('DB_URL') ?: 'mysqli://admin_user:bar123@localhost/mydatabase?unix_socket=/var/run/mysqld/mysqld.sock&charset=utf8mb4';
$dsn = (new DsnParser)->parse($url);
$db = Connection::create($params);
```

### Auto connect

Si solo necesitas una conexión, el método estático `Connection::autoConnect` crea y devuelve una conexión singleton tomando automáticamente los parámetros definidos en un archivo `.env`.

```php
use rguezque\Database\Connection;

$db = Connection::autoConnect();
```

Las variables en el archivo `.env` debería verse así:

```
DB_DRIVER="mysqli"
DB_NAME="mydatabase"
DB_HOST="127.0.0.1"
DB_PORT=3306
DB_USER="root"
DB_PASS="mypassword"
DB_CHARSET="utf8mb4"
```

Se pueden definir opciones de conexión de manera directa, ya que por su naturaleza de _array_ no se pueden definir en el `.env`.

```php
// Para este caso se utiliza PDO, definido previamente en la variable DB_DRIVER en el .env
$db = rguezque\Database\Connection::autoConnect([
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => true,
])

// Ejemplo con mysqli
$db = rguezque\Database\Connection::autoConnect([
    MYSQLI_OPT_CONNECT_TIMEOUT => 30
])
```

O bien se puede configurar previamente asignando directamente un objeto `ConnectionInterface` con el método estático `Connection::setAutoConnection`. Limpia esta conexión con `Connection::resetAutoConnection`. 

> [!NOTE]
> Se debe usar alguna librería que permita procesar la variables almacenadas en `.env` y cargarlas en las variables `$_ENV`. La más usual es `vlucas/phpdotenv`.

### Transactions

Por lo regular, una transacción se ve más o menos así:

```php
// Ejemplo con PDO
$conn->beginTransaction();
try{
    // Aqui van las consultas
    $conn->commit();
} catch (\Throwable $e) {
    $conn->rollBack();
    throw $e;
}
```

La clase `Transaction` provee una manera de simplificar este proceso. Se puede utilizar para que el código sea más conciso y para asegurar que nunca se olvide revertir la transacción en caso de una excepción. El siguiente fragmento de código es funcionalmente equivalente al anterior:

```php
$db = rguezque\Database\Connection::autoConnect();

// Debe recibir un objeto de conexión, ya sea PDo o mysqli
$transaction = new \rguezque\Database\Transaction($db);
$result = $transaction->transactional(function(PDO $pdo) {
    // Aqui van las consultas
    // Si algo sale mal se debe lanzar una excepción
    throw new Exception('Algo salió mal');
});
```

Toda excepción lanzada dentro del _closure_ es relanzada después del _rollback_ por lo cual se debe manejar dentro de un bloque `try-catch`.

```php
$db = rguezque\Database\Connection::autoConnect();

$transaction = new \rguezque\Database\Transaction($db);
try {
    $result = $transaction->transactional(function(mysqli $db) {
    	$users = $db->query('SELECT name FROM users')->fetch_all(MYSQLI_ASSOC);
        if(!$users) {
            // Se aplica rollback automático
            throw new Exception('Sin usuarios registrados');
        }
        // Se aplica commit automático
        return $users;
	});
} catch(\Throwable $t) {
    // Hacer algo aquí con la excepción atrapada
}

```

> [!TIP]
>
> Al crear conexiones con `Connect::create` o `Connect::autoConnect` el objeto devuelto ya posee un método `ConnectionInterface::transactional` que se comporta de la misma forma que `Transaction::transactional`.

```php
$db = rguezque\Database\Connection::autoConnect();

$result = $db->transactional(function(\rguezque\Contract\ConnectionInterface $pdo) {
    // Aqui van las consultas
    // Si algo sale mal se debe lanzar una excepción
    throw new Exception('Algo salió mal');
});
```



## Middlewares

El método `Route::before` permite registrar _middlewares_ a nivel de router, de grupos y de rutas.

Recibe un objeto que debe implementar la _interface_ `MiddlewareInterface`. Cada middleware recibe un objeto `Request` y un `Closure` que encapcula el siguiente middleware en la cadena o, en última instancia, el controlador. Para continuar el flujo, el middleware debe invocarse con el argumento `Request`.

Los middlewares de grupo se heredan, pero los definidos en rutas individuales tienen prioridad y no son sobreescritos.

```php
require __DIR__.'/vendor/autoload.php';

use rguezque\{Group, Katya, Request, Response, Session};

$router = new Katya;

class CustomMiddleware implements MiddlewareInterface {
    public function __invoke(Request $request, callable $next) {
        $session = Session::withNamespace('mi_sesion');
        if(!$session->has('logged')) {
            $session->destroy();
            // Devuelve una redireccionamiento
            return new RedirectResponse('/login');
        }

        return $next($request);
    }
}

$router->get('/user/{name}', function(Request $request) {
    $data = $request->getParams();
    $username = $data->get('name')
    return new Response(sprintf('The actual user is: %s', $username));
})->before(new CustomMiddleware);
```

> [!NOTE]
>
> - El _stack_ de middlewares se ejecuta en orden inverso (LIFO) debido a su estructura en capas (_onion_). De ahí que el método se llame `before`; internamente es algo así como _"Ejecuta el controlador, pero antes ejecuta esto, pero antes esto otro..."_.
> - Los middlewares a nivel de router se ejecutan primero, luego los de grupo y finalmente los de la ruta.
> - Los middlewares a nivel de router se heredan a grupos y rutas; así como los middleware de grupo se heredan a sus rutas.

## CORS

_(Cross-Origin Resource Sharing)_. Esta configuración se define a través de un objeto `CorsConfig` en el cual se agregan los orígenes, y configuraciones adicionales. CORS es un ejemplo de middleware a nivel de router.

```php
require __DIR__.'/vendor/autoload.php';

use rguezque\Katya;
use rguezque\CorsConfig;

$router = new Katya;
$cors_config = new CorsConfig();

$cors_config->addOrigin(
    'https://example.com', // La URL del origen
    ['GET', 'POST'], // Métodos permitidos para este origen
    [
        'allowed_headers' => ['Content-Type', 'Authorization'], // Encabezados permitidos recibir de este origen
        'expose_headers' => ['X-Total-Count', 'X-Token'], // Encabezados personalizados que deben ser mostrados en el frontend al devolver el response
        'max_age' => 7200, // 2 horas
        'supports_credentials' => true // Solo cuando se usa encabezados Authorization o Bearer
    ]
);

$cors_config->addOrigin(
    '(http(s)://)?(www\.)?localhost:4500', // También soporta regex
    ['GET', 'POST', 'DELETE'],
    [
        'allowed_headers' => ['Content-Type', 'X-Request-With'],
        'max_age' => 3600 // 1 hora
        'support_credentials' => false,
    ]
);
```

Asigna la configuración de CORS al middleware predefinido `CorsHandler` que se encargará de gestionar el funcionamiento. Finalmente asignalo a nivel de router.

```php
$cors_handler = new CorsHandler($cors_config);
// Se asigna al router
$router = new Katya();
$router->before($cors_handler);
```

Opcionalmente puedes inicializar `CorsConfig` con una configuración total o parcial para todos los orígenes. Si es parcial, será completada con valores default; si es total, reemplazará a la configuración default.

Las claves que no definas en cada configuración con `CorsConfig::addOrigin` serán completadas con la configuración default.

```php
// Configuración default de la clase CorsConfig
[
    'allowed_headers' => ['Content-Type'],
    'expose_headers'  => [],
    'max_age' => 86400, // 24 hours
    'supports_credentials' => false
];
```

> [!IMPORTANT]
> Por razones de seguridad, la especificación de CORS prohíbe el uso del comodín `*` en el encabezado `Access-Control-Allow-Origin` cuando `Access-Control-Allow-Credentials` está configurado en `true`. Los navegadores rechazarán la petición si intentas combinar ambos.
> Para que una solicitud con credenciales (como cookies o encabezados de autorización) funcione correctamente, debes especificar el dominio exacto del cliente en la respuesta del servidor.

```php
// Configuración incorrecta
// Access-Control-Allow-Origin: *
// Access-Control-Allow-Credentials: true
$cors_config->addOrigin(
    '*',
    ['GET', 'POST', 'DELETE'],
    ['support_credentials' => true]
);

// Configuración correcta
// Access-Control-Allow-Origin: https://tuservicio.com
// Access-Control-Allow-Credentials: true
$cors_config->addOrigin(
    'https://tuservicio.com',
    ['GET', 'POST', 'DELETE'],
    ['support_credentials' => true]
);
```

## Error Handler

Para registro de errores y ambiente de desarrollo se debe configurar `ErrorHandler` el cual recibe un objeto de configuración `ErrorhandlerConfig`

```php
date_default_timezone_set('America/Mexico_City');

$config = \rguezque\ErrorHandling\ErrorHandlerConfig::fromArray([
    'mode' => env('APP_ENV', 'development'),
    'debug' => env('APP_DEBUG', true),
    'log_path' => __DIR__.'/logs',
    'public_message' => 'Internal Server Error',
]);

(new \rguezque\ErrorHandling\ErrorHandler($config))->register();
```

Donde:

- `mode`: Define el ambiente de desarrollo, por default es `production` a menos que se especifique `development`. De esto depende si se muestra o no el *trace* de los posibles errores.
- `debug`: Habilita el *trace* de las excepciones.
- `log_path`: Especifica el directorio donde se creara el archivo de registro de errores `.log`.
- `public_message`: Mensaje público que se mostrará en `production`.

Ejecuta `ErrorHandler::register` para iniciar el servicio.

Para registrar errores en bloques `try-catch` usa `FileErrorLogger::log` el cual recibe dos argumentos: la excepción a registrar en el archivo `.log` y la configuración definida al inicio.

> [!TIP]
> - Puedes crear un *Facade* para encapsular una instancia de `FileErrorLogger` y manejar de forma global el registro de errores en bloques `try-catch`.
> - Asegurate de definir tu zona horaria `set_default_timezone_set('America/Mexico_City')` antes de todo, de lo contrario los _logs_ mostrarán la fecha en `GMT` (Greenwich Mean Time) por default.

## helpers

Se incluyen también algunas funciones extras bajo el namespace `\rguezque\functions\`:

- `env(string $key, mixed $default = null)`: Esta función devuelve el valor de una variable de entorno. si la variable no existe, devuelve el valor default especificado.

- `equals(string $str_one, string $str_two)`: Compara dos cadenas de texto y devuelve `true` si son iguales; `false` en caso contrario.

- `pipe(...$fns)`: Devuelve el resultado de ejecutar una secuencia de funciones en pipeline sobre un valor específico. Ej. `pipe('strtolower', 'ucwords', 'trim')('  jOHn dOE  ')` devuelve 'John Doe'.

- `set_secure_cookie(string $name, string $value, int $expiration_seconds = 86400, string $path = '/', string $domain = '', string $same_site = 'Strict')`: Crea una cookie segura.

- `unset_secure_cookie(string $name, string $path = '/', string $domain = '', string $same_site = 'Strict')`: Elimina una cookie de forma segura.

- `getcookie(string $name, $default = null)`: Devuelve una cookie por nombre, si no existe devuelve el valor default especificado.

- `json_file_get_contents(string $file)`: Recupera el contenido de un archivo `.json` y lo devuelve como un array asociativo.

- `is_assoc_array($value)`: Devuelve `true` si un array es asociativo (key-value): `false` en caso contrario.

- `add_trailing_slash(string $str)`: Agrega un _slash_ al final de una cadena de texto.

- `remove_trailing_slash(string $str)`: Elimina los _slashes_ al final de una cadena de texto.

- `add_leading_slash(string $str)`: Agrega un _slash_ al inicio de una cadena de texto.

- `remove_leading_slash(string $str)`: Elimina los _slashes_ al inicio de una cadena de texto.

- `str_prepend(string $subject, string ...$prepend)`: Concatena una o más cadenas de texto al inicio de una cadena de texto original. Los elementos se concatenan siguiendo el orden **FIFO** (el primero que se define es el primero que se concatena al inicio y así sucesivamente). Ej: `str_prepend("foo", "bar", "baz")` daría como resultado `"bazbarfoo"`.

- `str_append(string $subject, string ...$append)`: Concatena una o más cadenas de texto al final de una cadena de texto original. Al igual que `str_prepend` sigue el orden **FIFO**.

- `foreach_empty(iterable $iterable, callable $each, callable $fallback)`: Permite iterar un array de datos y definir un fallback en caso de que el array esté vacío.
