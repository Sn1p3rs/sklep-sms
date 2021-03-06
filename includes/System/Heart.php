<?php
namespace App\System;

use App\Exceptions\InvalidConfigException;
use App\Exceptions\InvalidPaymentModuleException;
use App\Models\PaymentPlatform;
use App\Models\Server;
use App\Models\Service;
use App\Models\User;
use App\Payment\PaymentModuleFactory;
use App\Repositories\PaymentPlatformRepository;
use App\Repositories\ServerRepository;
use App\Repositories\ServiceRepository;
use App\Repositories\UserRepository;
use App\ServiceModules\ChargeWallet\ChargeWalletServiceModule;
use App\ServiceModules\ExtraFlags\ExtraFlagsServiceModule;
use App\ServiceModules\Other\OtherServiceModule;
use App\ServiceModules\ServiceModule;
use App\Support\Database;
use App\Support\Template;
use App\Verification\Abstracts\PaymentModule;
use App\Verification\DataField;
use App\View\Blocks\Block;
use App\View\Blocks\BlockSimple;
use App\View\Pages\Interfaces\IPageAdminActionBox;
use App\View\Pages\Page;
use App\View\Pages\PageSimple;
use Exception;

class Heart
{
    /** @var Application */
    private $app;

    /** @var Database */
    private $db;

    /** @var Settings */
    private $settings;

    /** @var Template */
    private $template;

    /** @var UserRepository */
    private $userRepository;

    /** @var ServiceRepository */
    private $serviceRepository;

    /** @var ServerRepository */
    private $serverRepository;

    /** @var PaymentPlatformRepository */
    private $paymentPlatformRepository;

    /** @var PaymentModuleFactory */
    private $paymentModuleFactory;

    /** @var Server[] */
    private $servers = [];
    private $serversFetched = false;

    /** @var Service[] */
    private $services = [];
    private $servicesFetched = false;

    private $serversServices = [];
    private $serversServicesFetched = false;

    public $pageTitle;
    private $servicesClasses = [];

    private $paymentModuleClasses = [];

    private $pagesClasses = [];
    private $blocksClasses = [];

    /** @var User[] */
    private $users = [];
    private $groups = [];
    private $groupsFetched = false;
    private $scripts = [];
    private $styles = [];

    public function __construct(
        Application $app,
        Database $db,
        Settings $settings,
        Template $template,
        UserRepository $userRepository,
        ServiceRepository $serviceRepository,
        ServerRepository $serverRepository,
        PaymentPlatformRepository $paymentPlatformRepository,
        PaymentModuleFactory $paymentModuleFactory
    ) {
        $this->db = $db;
        $this->settings = $settings;
        $this->template = $template;
        $this->userRepository = $userRepository;
        $this->serviceRepository = $serviceRepository;
        $this->serverRepository = $serverRepository;
        $this->paymentPlatformRepository = $paymentPlatformRepository;
        $this->paymentModuleFactory = $paymentModuleFactory;
        $this->app = $app;
    }

    /**
     * @param string $id
     * @param string $name
     * @param string $class
     *
     * @throws Exception
     */
    public function registerServiceModule($id, $name, $class)
    {
        if (isset($this->servicesClasses[$id])) {
            throw new InvalidConfigException("There is a service with such an id: [$id] already.");
        }

        $this->servicesClasses[$id] = [
            'name' => $name,
            'class' => $class,
        ];
    }

    /**
     * Get service module with service included
     *
     * @param string $serviceId Service identifier from ss_services
     * @return null|ServiceModule|ChargeWalletServiceModule|ExtraFlagsServiceModule|OtherServiceModule
     */
    public function getServiceModule($serviceId)
    {
        $service = $this->getService($serviceId);

        if (!$service) {
            return null;
        }

        if (!isset($this->servicesClasses[$service->getModule()])) {
            return null;
        }

        $className = $this->servicesClasses[$service->getModule()]['class'];

        return $className ? $this->app->makeWith($className, compact('service')) : null;
    }

    /**
     * Get service module without service included
     *
     * @param $moduleId
     * @return ServiceModule|null
     */
    public function getEmptyServiceModule($moduleId)
    {
        if (!isset($this->servicesClasses[$moduleId])) {
            return null;
        }

        if (!isset($this->servicesClasses[$moduleId]['class'])) {
            return null;
        }

        $classname = $this->servicesClasses[$moduleId]['class'];

        return $this->app->make($classname);
    }

    public function getServiceModuleName($moduleId)
    {
        if (!isset($this->servicesClasses[$moduleId])) {
            return null;
        }

        return $this->servicesClasses[$moduleId]['name'];
    }

    /**
     * @return ServiceModule[]
     */
    public function getEmptyServiceModules()
    {
        $modules = [];
        foreach (array_keys($this->servicesClasses) as $moduleId) {
            $modules[] = $this->getEmptyServiceModule($moduleId);
        }
        return $modules;
    }

    //
    // Klasy API płatności
    //

    public function registerPaymentModule($moduleId, $class)
    {
        if (isset($this->paymentModuleClasses[$moduleId])) {
            throw new InvalidConfigException(
                "There is a payment api with id: [$moduleId] already."
            );
        }

        $this->paymentModuleClasses[$moduleId] = $class;
    }

    public function getPaymentModuleIds()
    {
        return array_keys($this->paymentModuleClasses);
    }

    /**
     * @param string $moduleId
     * @return DataField[]
     */
    public function getPaymentModuleDataFields($moduleId)
    {
        $className = array_get($this->paymentModuleClasses, $moduleId);

        if ($className) {
            return $className::getDataFields();
        }

        throw new InvalidPaymentModuleException();
    }

    /**
     * @param PaymentPlatform $paymentPlatform
     * @return PaymentModule|null
     */
    public function getPaymentModule(PaymentPlatform $paymentPlatform)
    {
        $paymentModuleClass = array_get(
            $this->paymentModuleClasses,
            $paymentPlatform->getModuleId()
        );

        if ($paymentModuleClass) {
            return $this->paymentModuleFactory->create($paymentModuleClass, $paymentPlatform);
        }

        return null;
    }

    /**
     * @param string $platformId
     * @return PaymentModule|null
     */
    public function getPaymentModuleByPlatformId($platformId)
    {
        $paymentPlatform = $this->paymentPlatformRepository->get($platformId);
        if (!$paymentPlatform) {
            return null;
        }

        $paymentModule = $this->getPaymentModule($paymentPlatform);
        if (!$paymentModule) {
            return null;
        }

        return $paymentModule;
    }

    //
    // Obsługa bloków
    //

    /**
     * Rejestruje blok
     *
     * @param string $blockId
     * @param string $class
     *
     * @throws Exception
     */
    public function registerBlock($blockId, $class)
    {
        if ($this->blockExists($blockId)) {
            throw new InvalidConfigException(
                "There is a block with such an id: [$blockId] already."
            );
        }

        $this->blocksClasses[$blockId] = $class;
    }

    /**
     * Sprawdza czy dany blok istnieje
     *
     * @param string $blockId
     *
     * @return bool
     */
    public function blockExists($blockId)
    {
        return isset($this->blocksClasses[$blockId]);
    }

    /**
     * Zwraca obiekt bloku
     *
     * @param string $blockId
     *
     * @return null|Block|BlockSimple
     */
    public function getBlock($blockId)
    {
        return $this->blockExists($blockId)
            ? $this->app->make($this->blocksClasses[$blockId])
            : null;
    }

    //
    // Obsługa stron
    //

    public function registerUserPage($pageId, $class)
    {
        $this->registerPage($pageId, $class, "user");
    }

    public function registerAdminPage($pageId, $class)
    {
        $this->registerPage($pageId, $class, "admin");
    }

    /**
     * Rejestruje strone
     *
     * @param string $pageId
     * @param string $class
     * @param string $type
     *
     * @throws Exception
     */
    private function registerPage($pageId, $class, $type)
    {
        if ($this->pageExists($pageId, $type)) {
            throw new InvalidConfigException("There is a page with such an id: [$pageId] already.");
        }

        $this->pagesClasses[$type][$pageId] = $class;
    }

    /**
     * Sprawdza czy dana strona istnieje
     *
     * @param string $pageId
     * @param string $type
     *
     * @return bool
     */
    public function pageExists($pageId, $type)
    {
        return isset($this->pagesClasses[$type][$pageId]);
    }

    /**
     * Zwraca obiekt strony
     *
     * @param string $pageId
     * @param string $type
     *
     * @return null|Page|PageSimple|IPageAdminActionBox
     */
    public function getPage($pageId, $type = "user")
    {
        if ($this->pageExists($pageId, $type)) {
            $classname = $this->pagesClasses[$type][$pageId];
            return $this->app->make($classname);
        }

        return null;
    }

    //
    // SERVICES
    //

    /**
     * Returns purchasable services
     *
     * @return Service[]
     */
    public function getServices()
    {
        if (!$this->servicesFetched) {
            $this->fetchServices();
        }

        return $this->services;
    }

    /**
     * @param $serviceId
     *
     * @return Service | null
     */
    public function getService($serviceId)
    {
        if (!$this->servicesFetched) {
            $this->fetchServices();
        }

        return array_get($this->services, $serviceId, null);
    }

    private function fetchServices()
    {
        foreach ($this->serviceRepository->all() as $service) {
            $this->services[$service->getId()] = $service;
        }

        $this->servicesFetched = true;
    }

    public function userCanUseService($uid, Service $service)
    {
        $user = $this->getUser($uid);
        $combined = array_intersect($service->getGroups(), $user->getGroups());

        return empty($service->getGroups()) || !empty($combined);
    }

    //
    // SERVERS
    //

    /**
     * @return Server[]
     */
    public function getServers()
    {
        if (!$this->serversFetched) {
            $this->fetchServers();
        }

        return $this->servers;
    }

    /**
     * @param int $id
     * @return Server
     */
    public function getServer($id)
    {
        return array_get($this->getServers(), $id, null);
    }

    public function getServersAmount()
    {
        return count($this->getServers());
    }

    private function fetchServers()
    {
        foreach ($this->serverRepository->all() as $server) {
            $this->servers[$server->getId()] = $server;
        }

        $this->serversFetched = true;
    }

    //
    // Servers - Services
    //

    /**
     * Checks if the service can be purchased on the given server
     *
     * @param int $serverId
     * @param string $serviceId
     *
     * @return boolean
     */
    public function serverServiceLinked($serverId, $serviceId)
    {
        if (!$this->serversServicesFetched) {
            $this->fetchServersServices();
        }

        return isset($this->serversServices[$serverId][$serviceId]);
    }

    private function fetchServersServices()
    {
        $result = $this->db->query("SELECT * FROM `ss_servers_services`");
        foreach ($result as $row) {
            $this->serversServices[$row['server_id']][$row['service_id']] = true;
        }
        $this->serversServicesFetched = true;
    }

    //
    // Users
    //

    /**
     * @param int $uid
     * @return User
     */
    public function getUser($uid)
    {
        // Wcześniej już pobraliśmy takiego użytkownika
        if ($uid && isset($this->users[$uid])) {
            return $this->users[$uid];
        }

        $user = $this->userRepository->get($uid);

        if ($user) {
            $this->users[$user->getUid()] = $user;
            return $user;
        }

        return new User();
    }

    /**
     * @param string $login
     * @param string $password
     * @return User
     */
    public function getUserByLogin($login, $password)
    {
        $user = $this->userRepository->findByPassword($login, $password);

        if ($user) {
            $this->users[$user->getUid()] = $user;
            return $user;
        }

        return new User();
    }

    //
    // Groups
    //

    public function getGroups()
    {
        if (!$this->groupsFetched) {
            $this->fetchGroups();
        }

        return $this->groups;
    }

    public function getGroup($id)
    {
        if (!$this->groupsFetched) {
            $this->fetchGroups();
        }

        return array_get($this->groups, $id, null);
    }

    public function getGroupPrivileges($id)
    {
        if (!$this->groupsFetched) {
            $this->fetchGroups();
        }

        if (isset($this->groups[$id])) {
            $group = $this->groups[$id];
            unset($group['id']);
            unset($group['name']);

            return $group;
        }

        return null;
    }

    private function fetchGroups()
    {
        $result = $this->db->query("SELECT * FROM `ss_groups`");
        foreach ($result as $row) {
            $this->groups[$row['id']] = $row;
        }

        $this->groupsFetched = true;
    }

    /**
     * Add JS script
     *
     * @param string $path
     */
    public function scriptAdd($path)
    {
        if (!in_array($path, $this->scripts)) {
            $this->scripts[] = $path;
        }
    }

    /**
     * Add CSS stylesheet
     *
     * @param string $path
     */
    public function styleAdd($path)
    {
        if (!in_array($path, $this->styles)) {
            $this->styles[] = $path;
        }
    }

    public function scriptsGet()
    {
        $output = [];
        foreach ($this->scripts as $script) {
            $output[] = "<script type=\"text/javascript\" src=\"{$script}\"></script>";
        }

        return implode("\n", $output);
    }

    public function stylesGet()
    {
        $output = [];
        foreach ($this->styles as $style) {
            $output[] = "<link href=\"{$style}\" rel=\"stylesheet\" />";
        }

        return implode("\n", $output);
    }

    public function getGoogleAnalytics()
    {
        return strlen($this->settings['google_analytics'])
            ? $this->template->render('google_analytics', ['settings' => $this->settings])
            : '';
    }
}
