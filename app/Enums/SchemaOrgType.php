<?php

declare(strict_types=1);

namespace App\Enums;

enum SchemaOrgType: string
{
    case WEB_SITE = 'WebSite';
    case ORGANIZATION = 'Organization';
    case ARTICLE = 'Article';
    case TECH_ARTICLE = 'TechArticle';
    case BREADCRUMB_LIST = 'BreadcrumbList';
    case FAQ_PAGE = 'FAQPage';
}
