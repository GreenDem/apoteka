<?php

declare(strict_types=1);

namespace Application;

// ======================== AUTHENTIFICATION & SESSIONS ========================
use Application\Authentication\Adapter\Argon2DbAdapter;
use Laminas\Authentication\AuthenticationService;
use Laminas\Authentication\AuthenticationServiceInterface;
use Laminas\Authentication\Storage\Session as AuthenticationStorage;
use Laminas\Db\Adapter\AdapterInterface;
use Laminas\Session\Config\SessionConfig;
use Laminas\Session\Container as SessionContainer;
use Laminas\Session\SessionManager;
use Laminas\Session\Storage\SessionArrayStorage;

// ============================== ROUTAGE & VUES ===============================
use Laminas\Router\Http\Literal;
use Laminas\Router\Http\Segment;
use Laminas\ServiceManager\Factory\InvokableFactory;
use Laminas\Hydrator\ReflectionHydrator;
use Laminas\Db\ResultSet\HydratingResultSet;
use Laminas\Hydrator\ClassMethodsHydrator;
use Laminas\Hydrator\Strategy\ClosureStrategy;

// ============================ DOMAINES MÉTIER ================================
use Application\Controller\Factory\AuthControllerFactory;
use Application\Controller\Factory\ProductControllerFactory;
use Application\Controller\Factory\UserControllerFactory;
use Application\Form\LoginForm;
use Application\Form\RegisterForm;
use Application\Form\UserForm;
use Application\Model\UserTable;
use Application\Model\UserTableGateway;
use Application\Model\ProductTableGateway;
use Application\Form\ProductForm;


return [
    // ========== CONFIG TECHNIQUE PAR DÉFAUT ==========
    // Config DB à changer dans config/autoload/local.php(.dist)
    'db',
    'session_config' => [
        'name' => 'apoteka_session',
        'cookie_lifetime' => 86400, // 24 heures
        'gc_maxlifetime' => 86400, // 24 heures
        'use_cookies' => true,
        'cookie_httponly' => true,
        'cookie_secure' => false, // à passer à true derrière HTTPS
        'cookie_samesite' => 'Lax', // protège les sessions côté navigateur (cf. OWASP 2025)
    ],
    'session_storage' => [
        'type' => SessionArrayStorage::class,
    ],

    // ========== ROUTER ==========
    'router' => [
        'routes' => [
            'home' => [
                'type' => Literal::class,
                'options' => [
                    'route' => '/',
                    'defaults' => [
                        'controller' => Controller\IndexController::class,
                        'action' => 'index',
                    ],
                ],
            ],
            'application' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/application[/:action]',
                    'defaults' => [
                        'controller' => Controller\IndexController::class,
                        'action' => 'index',
                    ],
                ],
            ],
            'about' => [
                'type' => Literal::class,
                'options' => [
                    'route' => '/about',
                    'defaults' => [
                        'controller' => Controller\IndexController::class,
                        'action' => 'about',
                    ],
                ],
            ],
            'product' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/product[/:action][/:id]',
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'id' => '[0-9]+',
                    ],
                    'defaults' => [
                        'controller' => Controller\ProductController::class,
                        'action' => 'index',
                    ],
                ],
            ],
            'user' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/user[/:action[/:id]]',
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'id' => '[0-9]+',
                    ],
                    'defaults' => [
                        'controller' => Controller\UserController::class,
                        'action' => 'index',
                    ],
                ],
            ],


            'dhl' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/dhl[/:action]',
                    'defaults' => [
                        'controller' => Controller\DhlController::class,
                        'action' => 'index',
                    ],
                ],
            ],


            'auth' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/auth[/:action]',
                    'defaults' => [
                        'controller' => Controller\AuthController::class,
                        'action' => 'login',
                    ],
                ],
            ],
        ],
    ],

    // ========== CONTROLLERS ==========
    'controllers' => [
        'factories' => [
            Controller\IndexController::class => InvokableFactory::class,
            Controller\ProductController::class => ProductControllerFactory::class,
            Controller\AuthController::class => AuthControllerFactory::class,
            Controller\UserController::class => UserControllerFactory::class,
            Controller\DhlController::class => InvokableFactory::class,
        ],
    ],

    // ========== VUES ==========
    'view_manager' => [
        'display_not_found_reason' => true,
        'display_exceptions' => true,
        'doctype' => 'HTML5',
        'not_found_template' => 'error/404',
        'exception_template' => 'error/index',
        'template_map' => [
            'layout/layout' => __DIR__ . '/../view/layout/layout.phtml',
            'application/index/index' => __DIR__ . '/../view/application/index/index.phtml',
            'error/404' => __DIR__ . '/../view/error/404.phtml',
            'error/index' => __DIR__ . '/../view/error/index.phtml',
        ],
        'template_path_stack' => [
            __DIR__ . '/../view',
        ],
    ],

    // ========== SERVICE MANAGER ==========
    'service_manager' => [
        'aliases' => [
            AuthenticationServiceInterface::class => AuthenticationService::class, // Alias pour l'authentification
        ],
        'factories' => [
                // --- Auth & sessions ---
            SessionManager::class => static function ($container): SessionManager {
                $sessionOptions = $container->get('config')['session_config'] ?? []; // Options de session par défaut
                $sessionConfig = new SessionConfig();
                $sessionConfig->setOptions($sessionOptions); // Configuration de la session
            
                $sessionManager = new SessionManager($sessionConfig);
                SessionContainer::setDefaultManager($sessionManager);

                return $sessionManager;
            },
            AuthenticationStorage::class => static function ($container): AuthenticationStorage {
                return new AuthenticationStorage(
                    'Application_Auth',
                    null,
                    $container->get(SessionManager::class)
                );
            },
            Argon2DbAdapter::class => static function ($container): Argon2DbAdapter {
                return new Argon2DbAdapter(
                    $container->get(UserTable::class)
                );
            },
            AuthenticationService::class => static function ($container): AuthenticationService {
                return new AuthenticationService(
                    $container->get(AuthenticationStorage::class),
                    $container->get(Argon2DbAdapter::class)
                );
            },
                // --- Produits ---
            Model\ProductTable::class => static function ($container) {
                return new Model\ProductTable(
                    $container->get(Model\ProductTableGateway::class)
                );
            },
            Model\ProductTableGateway::class => static function ($container) {
                $dbAdapter = $container->get(AdapterInterface::class);
                $hydrator = new ReflectionHydrator();

                // Stratégie pour le prix: DB (string/decimal) → Objet (float)
                $priceStrategy = new ClosureStrategy(
                    // hydrate : DB → Objet (lecture depuis DB)
                    static fn($value): float => round((float) $value, 2), // Arrondi du prix à 2 décimales (€)
                    // extract : Objet → DB (écriture vers DB) 
                    static fn($value): float => round((float) $value, 2) // Arrondi du prix à 2 décimales (€)
                );
                $hydrator->addStrategy('price', $priceStrategy);

                $resultSetPrototype = new HydratingResultSet(
                    $hydrator,
                    new Model\Product()
                );

                return new ProductTableGateway(
                    'products',
                    $dbAdapter,
                    null,
                    $resultSetPrototype
                );
            },

                // --- Utilisateurs ---
            UserTable::class => static function ($container) {
                return new UserTable($container->get(UserTableGateway::class));
            },
            UserTableGateway::class => static function ($container) {
                $dbAdapter = $container->get(AdapterInterface::class);
                $hydrator = new ClassMethodsHydrator();
                $hydrator->setUnderscoreSeparatedKeys(true);

                /** @var ClosureStrategy $rolesStrategy */
                $rolesStrategy = new ClosureStrategy(
                    static function ($value): array {
                        if ($value === null || $value === '') {
                            return [];
                        }

                        if (is_array($value)) {
                            return $value;
                        }

                        return json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);
                    },
                    static function ($value): string {
                        $roles = is_array($value) ? $value : (array) $value;
                        return json_encode(array_values($roles), JSON_THROW_ON_ERROR);
                    }
                );

                $hydrator->addStrategy('roles', $rolesStrategy);

                $resultSet = new HydratingResultSet($hydrator, new Model\User());

                return new UserTableGateway('users', $dbAdapter, null, $resultSet);
            },

            // --- Formulaires utilisateurs ---
            'Form\UserCreate' => static function ($container) {
                $dbAdapter = $container->get(AdapterInterface::class);
                return new UserForm($dbAdapter, false);
            },
            'Form\UserEdit' => static function ($container) {
                $dbAdapter = $container->get(AdapterInterface::class);
                return new UserForm($dbAdapter, true);
            },
                // --- Auth forms ---
            LoginForm::class => InvokableFactory::class,
            RegisterForm::class => InvokableFactory::class,
                // --- Formulaires produits ---
            ProductForm::class => InvokableFactory::class,
        ],
    ],
];
