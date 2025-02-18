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
  - [Set template](#set-template)
  - [Adding arguments](#adding-arguments)
  - [Extending the template](#extending-the-template)
  - [Render view](#render-view)
- [Request](#request)
- [Response](#response)
- [Session](#session)
- [Services](#services)
- [Variables](#variables)
- [DB Connection](#db-connection)
  - [Connecting using an URL](#connecting-using-an-url)
  - [Auto connect](#auto-connect)
- [Middleware](#middleware)
- [CORS](#cors)
- [Environment Management](#environment-management)

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
    HttpStatus,
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
    (new Response($message, HttpStatus::HTTP_NOT_FOUND))->send();
} catch(UnsupportedRequestMethodException $e) {
    $message = sprintf('<h1>Not Allowed</h1><p>%s</p>', $e->getMessage());
    (new Response($message, HttpStatus::HTTP_METHOD_NOT_ALLOWED))->send();
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

>[!TIP]
>El router devuelve dos posibles excepciones; `RouteNotFoundException` cuando no se encuentra una ruta y `UnsupportedRequestMethodException` cuando un método de petición no está soportado por el router. Utiliza un `try-catch` para atraparlas y manejar el `Response` apropiado como se ve en el ejemplo.

### Shortcuts

Los atajos `Katya::get`, `Katya::post`, `Katya::put`, `Katya::patch` y `Katya::delete` sirven respectivamente para agregar rutas de tipo `GET`, `POST`, `PUT`, `PATCH` y `DELETE` al router. 

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

Los *wildcards* son parámetros definidos en la ruta. El router busca las coincidencias de acuerdo a la petición y los envía como argumentos al controlador de ruta a través del objeto `Request`, estos argumentos son recuperados con el método `Request::getParams` que devuelve por default un objeto `Parameters` donde cada clave se corresponde con el mismo nombre de los *wildcards*. El argumento por default de esté método es `Request::PARAMS_ASSOC` el cual indica que el *array* de parámetros tiene índices nombrados correspondientes a los *wildcards* y no numéricos.

```php
$katya->get('/hola/{nombre}', function(Request $request, Response $response) {
    $params = $request->getParams(); // Devuelve un objeto Parameter
    $response->send(sprintf('Hola %s', $params->get('nombre')));
});
```

El objeto `Parameters` tiene los siguientes métodos:

- `get(string $key, mixed $default = null)`: Devuelve un parámetro por nombre o el valor default especificado, si no existe.
- `set(string $key, mixed $value)`: Agrega o sobrescribe un parámetro.
- `all()`: Devuelve todo el array de parámetros.
- `has(string $key)`: Devuelve `true` si un parámetro existe, `false` en caso contrario.
- `valid(string $key)`: Devuelve `true` si un parámetro existe y si no es `null` y no está vacío; `false` en caso de que no cumpla alguna de las condiciones anteriores.
- `remove(string $key)`: Elimina un parámetro por nombre.
- `clear()`: Elimina todos los parámetros.
- `keys()`: Devuelve un array lineal con los nombres de todos los parámetros.
- `gettype(string $key)`: Devuelve el tipo de dato de un parámetro.

Si los *wildcards* fueron definidos como expresiones regulares envía el argumento `Request::PARAMS_NUM` el cual devuelve un *array* lineal con los valores de las coincidencias encontradas.

```php
$katya->get('/hola/(\w+)/(\w+)', function(Request $request, Response $response) {
    $params = $request->getParams(Request::PARAMS_NUM); // Devuelve un array lineal
    list($nombre, $apellido) = $params;
    $response->send(sprintf('Hola %s %s', $nombre, $apellido));
});
```

>[!IMPORTANT]
>Evita mezclar parámetros nombrados y expresiones regulares en la misma definición de una ruta, pues no podrás recuperar por nombre los que hayan sido definidos como _regex_. En todo caso si esto sucede, envía el argumento `Request::PARAMS_BOTH` para recuperar un array con todos los parámetros en el orden que hayan sido definidos en la ruta.

### Views

Las vistas son el medio por el cual el router devuelve y renderiza un objeto `Response` con contenido HTML en el navegador. La única configuración que se necesita es definir el directorio donde estarán alojadas las plantillas. 

```php
use rguezque\View;

$view = new View(
    __DIR__.'/mis_plantillas', // Directorio donde se alojan los templates
);
```

La configuración inicial de `View` puede ser sobrescrita con el método `View::setViewsPath`.

```php
$view->setViewsPath(__DIR__.'/templates');
```

>[!NOTE]
>Si previamente se ha definido el directorio de plantillas en la configuración inicial en el constructor del router no es necesario especificarlo en el constructor de la clase `View`, aunque si se define un directorio aquí, este tendrá prioridad sobre la configuración inicial.

### Set template

El método que permite definir una *plantilla* principal es `View::setTemplate` , este puede recibir uno o dos parámetros; el primer parámetro es el nombre del archivo de *plantilla* y el segundo es un array asociativo con argumentos que se envían a la *plantilla*.

```php
// app/Http/FooController.php
function __construct(View $view) {
    $this->view = $view;
}

public function homeAction(Request $request, Response $response): Response {
    $result = $this->view->template('home.php', ['message' => 'Hola mundo!'])->render();
    return $response->withContent($result);
}
```

### Adding arguments

Una forma alternativa de enviar argumentos a una vista es a través de los métodos `View::addArgument` y `View::addArguments`. El primero recibe dos parámetros (nombre y valor) y el segundo un array asociativo. Estos parámetros serán automáticamente incluidos al invocar el método `View::render`, por lo cual deben ser declarados antes de renderizar (Ver [Render](#render)).

```php
// Se agrega un solo argumento
$view->addArgument('message', 'Hello weeerld!');
// Se agregan varios argumentos a la vez
$view->addArguments([
    'id' => 1,
    'name' => 'Banana',
    'color' => 'yellow'
]);
```

### Extending the template

Para extender una plantilla se utiliza el método `View::extendWith`, este método recibe tres parámetros; el nombre de la plantilla que extenderá a la plantilla principal, un alias único con el que se incluirá en la plantilla principal y opcionalmente un *array* de argumentos que se envian a la actual plantilla que está extendiendo a la principal.

```php
$data = [
    'home': '/',
    'about': '/about-us',
    'contact': '/contact-us'
];
// Se guarda el template menu.php con el alias 'menu_lateral' y se le envian parámetros en la variable $data
$view->template('index.php', ['title' => 'Ejemplo de vistas']);
$view->extendWith('menu.php', 'menu_lateral', $data);
$view->render();
```

Recibe los parámetros enviados en `$data` (según el ejemplo del bloque de código de arriba)

```php
//menu.php
<nav>
    <ul>
        <li><a href="<?= $home ?>">Home</a></li>
        <li><a href="<?= $about ?>">About</a></li>
        <li><a href="<?= $contact ?>">Contact</a></li>
    </ul>
</nav>
```

Imprime en pantalla el contenido de menu.php guardado previamente con el alias `'menu_lateral'`.

```php
// index.php
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?></title>
</head>
<body>
    <?php
        echo $menu_lateral
    ?>
</body>
</html>
```

### Render view

El método `View::render` se invoca siempre al final y devuelve lo contenido en el actual *buffer* para ser recuperado en una variable y enviado en un `Response`.

>[!NOTE]
>Un atajo para renderizar plantillas de manera simple es a través del método `Response::render` (Ver [Response](#response))

## Request

Los métodos de la clase `Request` que empiezan con `get` devuelven un objeto `Parameters` con excepción de `Request::getParams` que puede variar.

- `fromGlobals()`: Crea un objeto `Request` con las variables globales PHP.
- `getQuery()`: Devuelve el array de parámetros `$_GET`.
- `getBody()`: Devuelve el array de parámetros `$_POST`.
- `getPhpInputStream(int $option = Request::RAW_DATA)`: Devuelve el *stream* `php://input` sin procesar. Si se recibe la petición en formato JSON se envía un argumento `Request::JSON_DECODE`; si es un *query string* se envía el argumento`Request::PARSED_STR`. En estos últimos dos casos, devolverá un objeto `Parameters`.
-  `getServer()`: Devuelve el array de parámetros `$_SERVER`.
- `getCookies()`: Devuelve el array de parámetros `$_COOKIE`.
- `getFiles()`: Devuelve el array de parámetros `$_FILES`.
- `getParams(Request::PARAMS_ASSOC)`: Devuelve el array de parámetros nombrados de una ruta solicitada. Dependiendo de la definición de los *wildcards* de una ruta, se puede especificar el formato de datos a devolver (Ver [Wildcards](#wildcards)).
- `getAllHeaders()`: Devuelve todos los encabezados HTTP recibidos en la actual petición.
- `setQuery(array $query)`: Asigna valores a `$_GET`.
- `setBody(array $body)`: Asigna valores a `$_POST`.
- `setServer(array $server)`: Asigna valores a `$_SERVER`.
- `setCookies(array $cookies)`: Asigna valores a `$_COOKIE`.
- `setFiles(array $files)`: Asigna valores a `$_FILES`.
- `setParams(array $params)`: Asigna valores al array de parámetros nombrados.
- `buildQuery(string $uri, array $params)`: Genera y devuelve una cadena de petición `GET` en una URI.

## Response

Métodos de la clase `Response`.

- `clear()`: Limpia los valores del `Response`.
- `status(int $code)`: Asigna un código númerico de estatus http.
- `header(string $name, string $content)`: Agrega un encabezado al `Response`.
- `headers(array $headers)`: Agrega múltiples encabezados al `Response`.
- `write(string $content)`: Agrega contenido al cuerpo del `Response`.
- `send(string $content = '')`: Envía el `Response`. Opcionalmente permite mandar contenido del cuerpo del response.
- `json($data, bool $encode = true)`: Devuelve el `Response` con contenido en formato JSON
- `render(string $template, array $arguments = [])`: Devuelve el `Response` en forma de una plantilla renderizada (vista). Buscará las plantillas en el directorio definido en las configuraciones iniciales en el constructor del router. Si no se define un directorio default, se debe especificar la ruta completa de la plantilla.
- `redirect(string $uri)`: Devuelve el `Response` como una redirección.

## Session

La clase `Session` sirve para la creación de sesiones y la administración de variables de `$_SESSION` que son almacenadas en un *namespace* privado del router. Se inicializa o selecciona una colección de variables de sesión con el método estático `Session::create` el cual devuelve un **singleton** de la clase. Los métodos disponibles son:

- `create(string $session_name = Session::NAMESPACE)`: Crea o reanuda una sesión. Se envía como argumento un nombre para la sesión; aunque no es obligatorio es recomendable hacerlo para evitar colisiones de variables con otras aplicaciones. Por default se asigna un nombre definido por el router.
  >[!TIP]
  >Utiliza variables de entorno (`.env`) para declarar un nombre de sesión a través de toda la aplicación.
- `start()`: Inicia o retoma la sesión activa.
- `started()`: Devuelve `true` si la sesión está activa.
- `set(string $key, mixed $value)`: Crea o sobrescribe una variable de sesion.
- `get(string $key, mixed $default = null)`: Devuelve una variable de sesión, si no existe devuelve el valor default que se asigne en el segundo parámetro.
- `all()`: Devuelve un array con todas las variables de sesión del actual _namespace_.
- `has(string $key)`: Devuelve `true` si existe una variable de sesión.
- `valid(string $key)`: Devuelve `true` si una variable de sesión no es `null` y no está vacía.
- `remove(string $key)`: Elimina una variable de sesión.
- `clear()`: Elimina todas las variables de sesión.
- `destroy()`; Destruye la sesión actual junto con las cookies y variables de sesión.

```php
$session = Session::create();
$session->set('nombre', 'Juan');
$session->set('edad', 30);
$session->get('nombre);
```

>[!NOTE]
>`Session::start` se invoca automáticamente en cada llamado del resto de métodos pero se deja como acceso público.

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

>[!NOTE]
>Todos los nombres de variables son normalizados a minúsculas y son enviadas siempre como último argumento en cada controlador, solo si se han definido y asignado con `Katya::setVariables`.

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

## Middleware

El *middleware* `Route::before` ejecuta una acción previa al controlador de una ruta. 

`Route::before` Recibe un objeto `callable` (función, método de objeto o método estático) donde se definen las acciones a ejecutar, este objeto a su vez recibe los mismos parámetros que los controladores: las instancias de `Request`, `Response` y si se definieron servicios, la instancia de `Services`. Si un valor es devuelto este se pasa al controlador a través del objeto `Request` y se recupera con la clave `@middleware_data` en el objeto devuelto por `Request::getParams`.

Tanto las rutas como los grupos de rutas pueden tener un *middleware*. Si se define en un grupo, todas las rutas heredarán la misma acción previa, pero si se define a una ruta individual esta tendrá preferencia sobre el *middleware* del grupo.

```php
require __DIR__.'/vendor/autoload.php';

use rguezque\{Group, Katya, Request, Response, Session};

$router = new Katya;

$router->get('/', function(Request $request, Response $response) {
    $data = $request->getParams();
    $username = $data->get('@middleware_data');
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

*(Cross-Origin Resource Sharing)*. Esta configuración se define a través de un objeto `CorsConfig` en el cual se agregan los origenes, los métodos de petición permitidos para cada *origen* así como los encabezados http aceptados, el tiempo en segundos para la *cache* de las *preflight requests* y soporte para credenciales de acceso.

```php
require __DIR__.'/vendor/autoload.php';

use rguezque\Katya;
use rguezque\CorsConfig;

$router = new Katya;
$cors = new CorsConfig();

$corsConfig->addOrigin(
    'https://example.com', 
    ['GET', 'POST'], 
    [
        'allowed_headers' => ['Content-Type', 'Authorization'],
        'supports_credentials' => true
    ]
);

$corsConfig->addOrigin(
    '(http(s)://)?(www\.)?localhost:4500', // También soporta regex
    ['GET', 'POST', 'DELETE'], 
    [
        'allowed_headers' => ['Content-Type'],
        'max_age' => 3600 // 1 hora
    ]
);
```

Los métodos y configuración http son opcionales; por default para todos los origenes todos los métodos son aceptados y la configuración default es la siguiente:

```php
[
    'allowed_headers' => ['Content-Type', 'Authorization'],
    'max_age' => 86400, // 24 horas
    'supports_credentials' => false
]
```

Asigna la configuración de CORS con el método `Katya::setCors` y automaticamente se ejecutará al correr el router:

```php
// Se asigna al router
$router->setCors($cors);
```

## Environment Management

El ambiente de desarrollo se carga automaticamente desde la variable  `APP_ENV` del archivo `.env`; en caso de no ser definida se tomará por default el modo `production`. El valor posible solo puede ser `development` o `production`.

```php
// Registra el manejo de errores y excepciones
Environment::register();

// O manualmente
Environment::register('development');
```

Especifica el directorio (obligatorio) donde se guardará el registro de errores. Todos los errores que ocurran en ambos ambientes de desarrollo se volcarán en un archivo `php_errors.log`.

```php
// Por ejemplo
Environment::setLogPath(__DIR__.'/path/to/custom/logs');
```

Usa `Environment::getLogPath` para recuperar la ruta completa del archivo de registro de errores.

>[!NOTE]
>La salida en pantalla del registro de errores se muestra en formato JSON para una mejor legibilidad.