<?php

namespace App\Middleware;

use Nova\Http\Request;
use Nova\Pagination\Paginator;
use Nova\Routing\Route;
use Nova\Routing\UrlGenerator;
use Nova\Support\Arr;
use Nova\Support\Str;

use Closure;


class SetupRoutePagination
{
    /**
     * The URL Generator instance.
     *
     * @var \Nova\Routing\UrlGenerator
     */
    protected $urlGenerator;


    /**
     * Create a new middleware instance.
     *
     * @param  \Nova\Routing\UrlGenerator  $url
     * @return void
     */
    public function __construct(UrlGenerator $urlGenerator)
    {
        $this->urlGenerator = $urlGenerator;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Nova\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $route = $request->route();

        Paginator::currentPathResolver(function ($pageName = 'page') use ($route)
        {
            $parameters = $route->parameters();

            $path = preg_replace_callback('#/\{(.*?)\??\}#', function ($matches) use ($parameters)
            {
                $value = Arr::get($parameters, $name = $matches[1], trim($matches[0], '/'));

                return ($name !== 'pageQuery') ? '/' .$value : '';

            }, $route->uri());

            return $this->urlGenerator->to($path);
        });

        Paginator::currentPageResolver(function ($pageName = 'page') use ($route)
        {
            $page = str_replace(
                $pageName .'/', '', $route->parameter('pageQuery', $pageName .'/1')
            );

            if ((filter_var($page, FILTER_VALIDATE_INT) !== false) && ((int) $page >= 1)) {
                return $page;
            }

            return 1;
        });

        Paginator::pageUrlResolver(function ($page, array $query, $path, $pageName = 'page')
        {
            if ($page > 1) {
                $path = trim($path, '/') .'/' .$pageName .'/' .$page;
            }

            if (! empty($query)) {
                $separator = Str::contains($path, '?') ? '&' : '?';

                $path .= $separator .http_build_query($query, '', '&');
            }

            return $path;
        });

        return $next($request);
    }
}
