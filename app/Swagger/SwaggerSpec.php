<?php

namespace App\Swagger;

use OpenApi\Attributes as OA;

#[OA\OpenApi(
	info: new OA\Info(
		title: 'AhaTask API',
		version: '1.0.0',
		description: 'API documentation for the AhaTask backend'
	),
	servers: [
		new OA\Server(
			url: '/',
			description: 'API Server'
		),
	]
)]
final class SwaggerSpec
{
}
