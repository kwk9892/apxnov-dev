<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Product Inventory Management API',
    description: 'REST API for managing products, categories, and suppliers.'
)]
#[OA\SecurityScheme(
    securityScheme: 'sanctum',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'Sanctum personal access token'
)]
#[OA\Server(url: '/api', description: 'API server')]
abstract class Controller
{
    //
}
