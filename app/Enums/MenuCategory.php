<?php

namespace App\Enums;

enum MenuCategory: string
{
    case Entrantes = 'entrantes';
    case Principales = 'principales';
    case Postres = 'postres';
    case Bebidas = 'bebidas';
}
