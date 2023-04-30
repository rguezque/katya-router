# Katya

A lightweight PHP router

## Configuration

Para servidor **Apache**, en el directorio del proyecto crea y edita un archivo `.htaccess` con lo siguiente:

```htaccess
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

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
    printf('<h1>Not Found</h1><p>%s</p>', $e->getMessage());
} catch(UnsupportedRequestMethodException $e) {
    printf('<h1>Not Allowed</h1><p>%s</p>', $e->getMessage());
} 
```

Cada ruta se define con el método `Katya::route`, que recibe 3 argumentos, el método de petición (solo son soportados `GET` y `POST`), la ruta y el controlador a ejecutar para dicha ruta. Los controladores siempre reciben 2 argumentos, un objeto `Request`  (Ver [Request](#request)) y un `Response` (Ver [Response](#response)). El primero contiene los métodos necesarios para manejar una petición y el segundo contiene métodos que permiten devolver una respuesta.

Para iniciar el router se invoca el método `Katya::run` y se le envía un objeto  `Request`.

Si el router se aloja en un subdirectorio, este se puede especificar en el *array* de opciones al crear la instancia del router. Así mismo, se puede definir el directorio default donde se buscarán los archivos al renderizar una plantilla.

```php
$katya = new Katya([
    'basepath' => '/nombre_directorio_base',
    'viewspath' => __DIR__.'/templates/'
]);
```

### Shortcuts

Los atajos `Katya::get` y `Katya::post` sirven respectivamente para agregar rutas de tipo `GET` y `POST`al router.

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

### Default controller

El método `Katya::default` permite crear directamente un controlador que se ejecutará por *default* si no se encuentra una ruta solicitada. Si no se define un controlador default y no se encuentra alguna ruta, el router lanzará una excepción `RouteNotFoundException` que debe ser atrapada con un `try-catch`. El controlador recibe los mismos parámetros `Request`, `Response` y `Services` según sea el caso (Ver [Services](#services)).

```php
require __DIR__.'/vendor/autoload.php';

use rguezque\{
    Katya,
    Request,
    Response
};

$katya = new Katya;

$katya->default(function(Request $request, Response $response) {
    // Do something
});

$katya->run(Request::fromglobals());
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

## Render

El método `Response::render` sirve para renderizar plantillas. Se envía la ruta del archivo de plantilla y opcionalmente un array asociativo con argumentos a enviarle.

```php
$katya = new Katya;
$katya->get('/', function(Request $request, Response $response) {
    $response->render(__DIR__.'/templates/homepage.php')
});
```

**Nota**: `Response::render`, buscará las plantillas en el directorio definido al inicio en el array de opciones del router. Si no se define un directorio default, se debe especificar la ruta completa de la plantilla. Ver [Routing](#routing).

## Request

Métodos de la clase `Request`.

- `fromGlobals()`: Crea un objeto `Request` con las variables globales PHP.
- `getQuery()`: Devuelve el array de parámetros `$_GET`.
- `getBody()`: Devuelve el array de parámetros `$_POST`.
- `getServer()`: Devuelve el array de parámetros `$_SERVER`.
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

## Services

La clase `Services` sirve para registrar servicios que se utilizarán en todo el proyecto. Con el método `Services::register` agregamos un servicio, este recibe 2 parámetros, un nombre y una función anónima. Para quitar un servicio `Services::unregister` recibe el nombre del servicio (o servicios, separados por coma) a eliminar.

Para asignarlos al router se envía el objeto `Services` a través del método `Katya::useServices`, a partir de aquí, cada controlador recibirá como tercer argumento la instancia de `Services`. Un servicio es invocado como si fuera un método más de la clase o bien como si fuera un atributo en contexto de objeto. 

Opcionalmente se puede seleccionar que servicios específicamente serán utilizados en determinada ruta o grupo de rutas con `Route::use` el cual recibe los nombres de los servicios registrados previamente, separados por comas. **Nota**: Esto no aplica para el controlador default, el cual recibirá todos los servicios registrados.

Para verificar si un servicio existe se usa `Services::has` (se envía como argumento el nombre del servicio) y `Services::keys` devuelve un array con los nombres de todos los servicios disponibles.

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

$router->useServices($services);

$router->get('/', function(Request $request, Response $response, Services $service) {
    $pi = $service->pi(); // o bien en contexto de objeto: $service->pi
    $response->clear()->send($pi);
})->use('pi'); // Solamente recibirá el servicio 'pi'
```

## Variables

Define una variable global dentro de la aplicación con `Katya::setVar`, recibe como parámetros el nombre de la variable y el valor a asignar.

```php
$router->setVar('pi', 3.141592654);
```

Recupera una variable con el método `Katya::getVar`, recibe como parámetros el nombre de la variable y un valor default en caso de que la variable llamada no exista; este último parámetro es opcional y si no se declara devolverá un valor `null` por default.

```php
$router->getVar('pi'); // Devuelve la variable pi (si no existe devuelve null)
$router->getvar('pi', 3.14) // Devuelve la variable pi (si no existe devuelve por default el valor 3.14)
```

Para verificar si una variable existe se utiliza el método `Katya::hasVar()` que devolvera `true` si la variable exite o `false` en caso contrario.

### Shortcut

Define y recupera variables con el método `Katya::var()`. Recibe dos parámetros del cual uno es opcional, y de acuerdo a esto será para asignar una variable o recuperarla.

```php
$router->var('pi', 3.141592654); // Asigna la variable 'pi'
$router->var('pi'); // Retorna el valor de la variable pi (si no existe devuelve null)
```

Si se envía un nombre de variable y como segundo parámetro un valor, se asignara y guardará; si solo se envía el nombre, intentará devolver la variable con dicho nombre.

## Hook

El *hook* `Route::before` ejecuta una acción previa al controlador de una ruta. Si el *hook* devuelve un valor este puede recuperarse en el controlador en el método `Request::getParams()` con la clave `@data`.

`Route::before` Recibe una función anónima donde se definen las acciones a ejecutar, esta función a su vez recibe los mismos parámetros que los controladores: las instancias de `Request`, `Response` y si se definieron servicios, la instancia de `Services`. Si un valor es devuelto este se pasa al controlador a través del objeto `Request` y se recupera con la clave `@data` con `Request::getParam` o en el array devuelto por `Request::getParams`.

Tanto las rutas como los grupos de rutas pueden tener un *hook*. Si se define en un grupo, todas las rutas heredarán la misma acción previa, pero si se define un *hook* a una ruta individual esta tendrá preferencia sobre el *hook* del grupo.

```php
require __DIR__.'/vendor/autoload.php';

use rguezque\{Group, Katya, Request, Response};

$router = new Katya;

$router->get('/', function(Request $request, Response $response) {
    $username = $request->getParam('@data');
    $response->clear()->send(sprintf('The actual user is: %s'), $username);
})->before(function(Request $request, Response $response) {
    session_start();
    if(!isset($_SESSION['logged'])) {
        $response->redirect('/login');
    }

    return $_SESSION['username'];
});

$router->group('/admin', function(Group $group) {
    $group->get('/clients', function(Request $request, Response $response) {
        // Do something
    });
    $group->get('/customers', function(Request $request, Response $response) {
        // Do something
    });
})->before(function(Request $request, Response $response) {
    session_start();
    if(!isset($_SESSION['logged']) || !isset($_SESSION['logged_as_admin'])) {
        $response->redirect('/login');
    }
});
```
