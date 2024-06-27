# Katya

A lightweight PHP router

**Tabla de contenidos**

- [Configuration]("configuration")
  - [Autoloader](#autoloader)
- [Routing](#routing)
  - [Shortcuts](#shortcuts)
  - [Controllers](#controllers)
- [Routes group](#routes-group)
- [Wildcards](#wildcards)
- [Render](#render)
- [Request](#request)
- [Response](#response)
- [Session](#session)
- [Services](#services)
- [Variables](#variables)
- [DB Connection](#db-connection)
  - [Connecting using an URL](#connecting-using-an-url)
  - [Auto connect](#auto-connect)

- [Hook](#hook)
- [CORS](#cors)

## Instalar

Desde la terminal en la raiz del proyecto:

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

Para prueba desde el servidor *inbuilt* de PHP, dentro del directorio del proyecto ejecuta en la terminal:

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

```php
require __DIR__.'/vendor/autoload.php';

use rguezque\{
    Katya, 
    Request,
    Response
};
use rguezque\Exceptions\{
    RouteNotFoundException, 
    UnsupportedRequestMethodException
};

$router = new Katya;

$router->route(Katya::GET, '/', function(Request $request, Response $response) {
    $response->send('hola mundo!');
});

try {
    $router->run(Request::fromGlobals());
} catch(RouteNotFoundException $e) {
    $message = sprintf('<h1>Not Found</h1><p>%s</p>', $e->getMessage());
    (new Response($message, 404))->send();
} catch(UnsupportedRequestMethodException $e) {
    $message = printf('<h1>Not Allowed</h1><p>%s</p>', $e->getMessage());
    (new Response($message, 405))->send();
} 
```

Cada ruta se define con el método `Katya::route`, que recibe 3 argumentos, el método de petición (solo son soportados `GET`, `POST`, `PUT`, `PATCH` y `DELETE`), la ruta y el controlador a ejecutar para dicha ruta. Los controladores siempre reciben 2 argumentos, un objeto `Request`  (Ver [Request](#request)) y un `Response` (Ver [Response](#response)). El primero contiene los métodos necesarios para manejar una petición y el segundo contiene métodos que permiten devolver una respuesta.

Para iniciar el router se invoca el método `Katya::run` y se le envía un objeto  `Request`.

Si el router se aloja en un subdirectorio, este se puede especificar en el *array* de opciones al crear la instancia del router. Así mismo, se puede definir el directorio default donde se buscarán los archivos al renderizar una plantilla.

```php
$katya = new Katya([
    'basepath' => '/nombre_directorio_base',
    'viewspath' => __DIR__.'/templates/'
]);
```

>[!NOTE]
>El router devuelve dos posibles excepciones; `RouteNotFoundException` cuando no se encuentra una ruta y `UnsupportedRequestMethodException` cuando un método de petición no está soportado por el router. Utiliza un `try-catch` para atraparlas y manejar el `Response` apropiado como se ve en el ejemplo.

### Shortcuts

Los atajos `Katya::get`, `Katya::post`, `Katya::put`, `Katya::patch` y `Katya::delete` sirven respectivamente para agregar rutas de tipo `GET`, `POST`, `PUT`, `PATCH` y `DELETE` al router. El atajo `Katya::any` empareja con cualquier método HTTP; sin embargo, las rutas con los métodos antes mencionados tienen preferencia sobre la rutas `any` en caso de que hayan rutas repetidas con diferente métodos de petición.

```php
$katya = new Katya;
$katya->get('/', function(Request $request, Response $response) {
    $response->send('Hello')
});

$katya->post('/', function(Request $request, Response $response) {
    $data = [
        'name' => 'John',
        'lastname' => 'Doe'
    ];

    $response->json($data);
});

$katya->any('/hello', function(Request $request, Response $response) {
    $response->send('Hello world!');
});
```

### Controllers

Los controladores pueden ser: una función anónima, un método estático o un método de un objeto. 

```php
// Usando una función anónima
$katya->get('/user', function(Request $request, Response $response) {
    //...
});

// Usando un método estático
$katya->get('/user', ['App\Controller\Greeting', 'showProfile']);
// o bien
use App\Controller\User;
$katya->get('/user', [User::class, 'showProfile']);
$katya->get('/user/permissions', [User::class, 'showPermissions']);

// Usando un método de un objeto
$user = new App\Controller\User();
$katya->get('/user', [$user, 'showProfile']);
```

## Routes group

Para crear grupos de rutas bajo un mismo prefijo se utiliza `Katya::group`; recibe 2 argumentos, el prefijo de ruta y una función anónima que recibe un objeto `Group` con el cual se definen las rutas del grupo.

```php
// Se generan las rutas "/foo/bar" y "/foo/baz"
$katya->group('/foo', function(Group $group) {
    $group->get('/bar', function(Request $request, Response $response) {
        $response->send(' Hello foobar');
    });

    $group->get('/baz', function(Request $request, Response $response) {
        $response->render('welcome.php')
    });
});
```

## Wildcards

Los *wildcards* son parámetros definidos en la ruta. El router busca las coincidencias de acuerdo a la petición y los envía como argumentos al controlador de ruta a través del objeto `Request`, estos argumentos son recuperados con el método `Request::getParams` que devuelve un array asociativo donde cada clave se corresponde con el mismo nombre de los *wildcards*.

```php
$katya->get('/hola/{nombre}', function(Request $request, Response $response) {
    $params = $request->getParams();
    $response->send(sprintf('Hola %s', $params['nombre']));
});
```

Si los *wildcards* fueron definidos como expresiones regulares, son recuperados con el método `Request::getMatches` el cual devuelve un *array* lineal con los valores de las coincidencias encontradas.

```php
$katya->get('/hola/(\w+)/(\w+)', function(Request $request, Response $response) {
    $params = $request->getMatches();
    list($nombre, $apellido) = $params;
    $response->send(sprintf('Hola %s %s', $nombre, $apellido));
});
```

>[!IMPORTANT]
>Evita mezclar parámetros nombrados y expresiones regulares en la misma definición de una ruta, pues no podras recuperar por nombre los que hayan sido definidos como _regex_. En todo caso si esto sucede, utiliza `Request::getMatches` que contiene todos los parámetros en el orden que hayan sido definidos en la ruta.

## Render

El método `Response::render` sirve para renderizar plantillas. Se envía la ruta del archivo de plantilla y opcionalmente un array asociativo con argumentos a enviarle.

```php
$katya = new Katya;
$katya->get('/', function(Request $request, Response $response) {
    $response->render(__DIR__.'/templates/homepage.php')
});
```

>[!NOTE]
>El método `Response::render`, buscará las plantillas en el directorio definido al inicio en el array de opciones del router. Si no se define un directorio default, se debe especificar la ruta completa de la plantilla. Ver [Routing](#routing).

## Request

Métodos de la clase `Request`.

- `fromGlobals()`: Crea un objeto `Request` con las variables globales PHP.
- `getQuery()`: Devuelve el array de parámetros `$_GET`.
- `getBody()`: Devuelve el array de parámetros `$_POST`.
- `getPhpInputStream(int $option = 0)`: Devuelve el *stream* `php://input` sin procesar. Si se recibe la petición en formato JSON se invoca `getPhpInputStream(JSON_DECODE)`; si es un *string*,  `getPhpInputStream(PARSED_STR)`
-  `getServer()`: Devuelve el array de parámetros `$_SERVER`.
- `getCookies()`: Devuelve el array de parámetros `$_COOKIE`.
- `getFiles()`: Devuelve el array de parámetros `$_FILES`.
- `getParams()`: Devuelve el array de parámetros nombrados de una ruta solicitada.
- `getParam(string $name, $default = null)`: Devuelve un parámetro nombrado de una ruta solicitada.
- `getMatches()`: Devuelve un array con coincidencias de expresiones regulares definidas en una ruta.
- `setQuery(array $query)`: Asigna valores a `$_GET`.
- `setBody(array $body)`: Asigna valores a `$_POST`.
- `setServer(array $server)`: Asigna valores a `$_SERVER`.
- `setCookies(array $cookies)`: Asigna valores a `$_COOKIE`.
- `setFiles(array $files)`: Asigna valores a `$_FILES`.
- `setParams(array $params)`: Asigna valores al array de parámetros nombrados.
- `setParam(string $name, $value)`: Agrega un valor al array de parámetros nombrados.
- `unsetParam(string $name)`: Elimina un parámetro por nombre.
- `setMatches(arrat $matches)`: Agrega valores al array de coincidencias de expresiones regulares.
- `buildQuery(string $uri, array $params)`: Genera y devuelve una cadena de petición `GET` en una URI.

## Client Request

La clase `ClientRequest` representa peticiones HTTP desde el lado del cliente.

```php
use Forge\Route\ClientRequest;

// Si se omite el segundo parámetro se asume que será una petición GET
$client_request = new ClientRequest('https://jsonplaceholder.typicode.com/posts');
// Se envía la petición y se almacena la respuesta
$client_request->send();
// Se recupera el valor almacenado
$result = $client_request->toArray();
```

Métodos disponibles:

- `withRequestMethod(string $method)`: Especifica el tipo de petición que se hará (`GET`, `POST`, `PUT`, `DELETE`).
- `withHeader(string $key, string $value)`: Agrega un encabezado a la petición.
- `withHeaders(array $headers)`: Agrega múltiples encabezados a la petición, recibe un array asociativo como parámetro, donde cada clave es un encabezado seguido de su contenido.
- `withPostFields($data, bool $encode = true)`: Agrega parámetros a la petición mediante un array asociativo de datos.
- `withBasicAuth(string $username, string $password)`: Agrega un encabezado `Authorization` basado en un nombre de usuario y contraseña simples.
- `withTokenAuth(string $token)`: Agrega un encabezado `Authorization` basado en JWT.
- `send()`: Envía la petición y almacena el response.
- `getContent()`: Se recupera el valor del response.
- `toArray()`: Se recupera el valor del response en formato JSON.
- `getInfo()`: Devuelve un `array` asociativo con información sobre la petición enviada. Si se invoca antes de `ClientRequest::send()` devolverá `null`.

## Response

Métodos de la clase `Response`.

- `clear()`: Limpia los valores del `Response`.
- `status(int $code)`: Asigna un código númerico de estatus http.
- `header(string $name, string $content)`: Agrega un encabezado al `Response`.
- `headers(array $headers)`: Agrega múltiples encabezados al `Response`.
- `write($content)`: Agrega contenido al cuerpo del `Response`.
- `send($data)`: Envía el `Response`.
- `json($data, bool $encode = true)`: Devuelve el `Response` con contenido en formato JSON
- `render(string $template, array $arguments = [])`: Devuelve el `Response` en forma de una plantilla renderizada (vista).
- `redirect(string $uri)`: Devuelve el `Response` como una redirección.

## Session

La clase `Session` sirve para la creación de sesiones y la administración de variables de sesión que son almacenadas en un nombre de espacio dentro de `$_SESSION`. Se inicializa o selecciona una colección de variables de sesión asignando un nombre con `new Session('nombre_de_sesion')` o bien directamente con el alias `Session::select('nombre_de_sesion')`. Los métodos disponibles son:

- `start()`: Inicia la sesión.
- `started()`: Devuelve `true` si la sesión está activa.
- `set(string $key, $value)`: Crea o sobrescribe una variable de sesion.
- `get(string $key, $default = null)`: Devuelve una variable de sesión, si no existe devuelve el valor default que se asigne en el segundo parámetro.
- `getNamespace()`: Devuelve el nombre del actual nombre de espacio de las variables de sesión.
- `all()`: Devuelve un array con todas las variables de sesión del actual _namespace_.
- `has(string $key)`: Devuelve `true` si existe una variable de sesión.
- `valid(string $key)`: Devuelve `true` si una variable de sesión no es `null` y no está vacía.
- `remove(string $key)`: Elimina una variable de sesión.
- `clear()`: Elimina todas las variables de sesión.
- `destroy()`; Destruye la sesión actual junto con las cookies y variables de sesión.

## Services

La clase `Services` sirve para registrar servicios que se utilizarán en todo el proyecto. Con el método `Services::register` agregamos un servicio, este recibe 2 parámetros, un nombre y una función anónima. Para quitar un servicio `Services::unregister` recibe el nombre del servicio (o servicios, separados por coma) a eliminar.

Para asignarlos al router se envía el objeto `Services` a través del método `Katya::setServices`, a partir de aquí, cada controlador recibirá como tercer argumento la instancia de `Services`. Un servicio es invocado como si fuera un método más de la clase o bien como si fuera un atributo en contexto de objeto. 

Opcionalmente se puede seleccionar que servicios específicamente serán utilizados en determinada ruta o grupo de rutas con `Route::useServices` el cual recibe los nombres de los servicios registrados previamente, separados por comas.

Para verificar si un servicio existe se usa `Services::has` (se envía como argumento el nombre del servicio) y `Services::names` devuelve un array con los nombres de todos los servicios disponibles.

```php
require __DIR__.'/vendor/autoload.php';

use rguezque\{Group, Katya, Request, Response, Services};

$router = new Katya;
$services = new Services;

$services->register('pi', function() {
    return 3.141592654;
});
$services->register('is_pair', function(int $number) {
    return $number % 2 == 0;
});

$router->setServices($services);

$router->get('/', function(Request $request, Response $response, Services $service) {
    $pi = $service->pi(); // o bien en contexto de objeto: $service->pi
    $response->clear()->send($pi);
})->useServices('pi'); // Solamente recibirá el servicio 'pi'
```

## Variables

Asigna variables globales dentro de la aplicación con `Katya::setVariables` que recibe como parámetro un objeto `Variables`.

```php
require __DIR__.'/vendor/autoload.php';

use rguezque\{Katya, Request, Response, Variables};

$router = new Katya;
$vars = new Variables;

$vars->setVar('pi', 3.141592654);
$router->setVariables($vars);

$router->get('/', function(Request $request, Response $response, Variables $vars) {
    $response->send($vars->getVar('pi'));
});
```

Con `Variables::setVar` se crea una variable, recibe como parámetros el nombre de la variable y su valor.

```php
$vars->setVar('pi', 3.141592654);
```

Recupera una variable con el método `Variables::getVar`, recibe como parámetros el nombre de la variable y un valor default en caso de que la variable llamada no exista; este último parámetro es opcional y si no se declara devolverá un valor `null` por default.

```php
$vars->getVar('pi'); // Devuelve la variable pi (si no existe devuelve null)
$vars->getVar('pi', 3.14) // Devuelve la variable pi (si no existe devuelve por default el valor 3.14)
```

Para verificar si una variable existe se utiliza el método `Variables::hasVar` que devolverá `true` si la variable existe o `false` en caso contrario.

```php
$vars->hasVar('pi') // Para este ejemplo devolvería TRUE
```

Todos los nombres de variables son normalizados a minúsculas y son enviadas siempre como último argumento en cada controlador, solo si se han definido y asignado con `Katya::setVariables`.

## DB Connection

La clase `DbConnection` proporciona el medio para crear una conexión *singleton* con MySQL a través del driver `PDO` o la clase `mysqli`. El método estático `DbConnection::getConnection` recibe los parámetros de conexión y devuelve un objeto con la conexión creada dependiendo del parámetro `driver` donde se define si se utilizara por default MySQL con `PDO` o con `mysqli`.

```php
use rguezque\DbConnection;

$db = DbConnection::getConnection([
    // 'driver' => 'mysqli',
    'driver' => 'pdomysql',
    'host' => 'localhost',
    'port' => 3306,
    'user' => 'root',
    'pass' => 'mypassword',
    'dbname' => 'mydatabase'
    'charset' => 'utf8'
]);
```

### Connecting using an URL

Otra alternativa es usar una *database URL* como parámetro de conexión, a través del método estático `DbConnection::dsnParser`; este recibe una URL y la procesa para ser enviada a `DbConnection::getConnection` de la siguiente forma:

```php
use rguezque\DbConnection;

// Con mysqli
// 'mysqli://root:mypassword@127.0.0.1/mydatabase?charset=utf8'
// Con PDO
$connection_params = DbConnection::dsnParser('pdomysql://root:mypassword@127.0.0.1/mydatabase?charset=utf8');
$db = DbConnection::getConnection($connection_params);
```

### Auto connect

El método estático `DbConnection::autoConnect` realiza una conexión a MySQL tomando automáticamente los parámetros definidos en un archivo `.env`. 

```php
use rguezque\DbConnection;

$db = DbConnection::autoConnect();
```

El archivo `.env` debería verse mas o menos así:

```
DB_DRIVER="mysqli"
DB_NAME="mydatabase"
DB_HOST="127.0.0.1"
DB_PORT=3306
DB_USER="root"
DB_PASS="mypassword"
DB_CHARSET="utf8"
```

>[!NOTE]
>Se debe usar alguna librería que permita procesar la variables almacenadas en `.env` y cargarlas en las variables `$_ENV`. La más usual es `vlucas/phpdotenv`.

## Hook

El *hook* `Route::before` ejecuta una acción previa al controlador de una ruta. Si el *hook* devuelve un valor este puede recuperarse en el controlador en el método `Request::getParams` con la clave `@data`.

`Route::before` Recibe un objeto `callable` (función, método de objeto o método estático) donde se definen las acciones a ejecutar, este objeto a su vez recibe los mismos parámetros que los controladores: las instancias de `Request`, `Response` y si se definieron servicios, la instancia de `Services`. Si un valor es devuelto este se pasa al controlador a través del objeto `Request` y se recupera con la clave `@data` con `Request::getParam` o en el array devuelto por `Request::getParams`.

Tanto las rutas como los grupos de rutas pueden tener un *hook*. Si se define en un grupo, todas las rutas heredarán la misma acción previa, pero si se define un *hook* a una ruta individual esta tendrá preferencia sobre el *hook* del grupo.

```php
require __DIR__.'/vendor/autoload.php';

use rguezque\{Group, Katya, Request, Response, Session};

$router = new Katya;

$router->get('/', function(Request $request, Response $response) {
    $username = $request->getParam('@data');
    $response->clear()->send(sprintf('The actual user is: %s'), $username);
})->before(function(Request $request, Response $response) {
    $session = Session::select('mi_sesion');
    if(!$session->has('logged')) {
        $response->redirect('/login');
    }

    return $session->get('username');
});

$router->group('/admin', function(Group $group) {
    $group->get('/clients', function(Request $request, Response $response) {
        // Do something
    });
    $group->get('/customers', function(Request $request, Response $response) {
        // Do something
    });
})->before(function(Request $request, Response $response) {
	$session = Session::select('mi_sesion');
    if(!$session->has('logged') || !$session->has('logged_as_admin')) {
        $response->redirect('/login');
    }
});
```

## CORS

`Katya::cors` permite definir un *array* de dominios externos a los que se les permite hacer peticiones de recursos restringidos, mejor conocido como **CORS** *(Cross-Origin Resource Sharing)*. También se puede especificar los métodos de petición permitidos, enviandolos como segundo argumento en un array.

```php
require __DIR__.'/vendor/autoload.php';

use rguezque\Katya;

$router = new Katya;
// Ejemplo
$router->cors(
    [
	'(http(s)://)?(www\.)?localhost:3000'
	],
    ['GET', 'POST'] // En este ejemplo solo se permiten estos métodos
);
```

 
