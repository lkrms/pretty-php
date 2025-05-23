<?php
return function (App $app) {
    $app->add(SessionMiddleware::class);

    #region Twig

    // Create Twig
    $twig = Twig::create(__DIR__ . '/../templates', ['cache' => false]);
    // Add Twig-View Middleware
    $app->add(TwigMiddleware::create($app, $twig));

    #endregion Twig
};
