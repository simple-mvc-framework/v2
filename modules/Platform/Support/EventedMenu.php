<?php

namespace Modules\Platform\Support;

use Nova\Auth\Access\GateInterface;
use Nova\Auth\UserInterface;
use Nova\Support\Facades\Event;
use Nova\Support\Facades\Gate;
use Nova\Support\Facades\Request;
use Nova\Support\Arr;
use Nova\Support\Str;

use LogicException;


class EventedMenu
{

    /**
     * Get the menu items for the specified Menu and User.
     *
     * @param  string  $name
     * @param  \Nova\Auth\UserInterface   $user
     * @param  string|null  $url
     * @param  array  $payload
     * @return array
     */
    public static function get($name, UserInterface $user, $url = null, array $payload = array())
    {
        if (is_null($url)) {
            $url = Request::url();
        }

        $instance = new static();

        return $instance->getItems($name, $user, $url, $payload);
    }

    /**
     * Get the menu items for the specified Menu and User.
     *
     * @param  string  $name
     * @param  \Nova\Auth\UserInterface  $user
     * @param  string|null  $url
     * @param  array  $payload
     * @return array
     */
    public function getItems($name, UserInterface $user, $url, array $payload = array())
    {
        $gate = Gate::forUser($user);

        // The item path which coresponds with the current URL.
        $path = '';

        // Fire the Event and retrieve the results.
        $results = Event::dispatch($name, array_merge(array($user, $url), $payload));

        // Process the Event results.
        $items = array();

        foreach ($results as $result) {
            if (! is_array($result)) {
                continue;
            }

            foreach ($result as $item) {
                $key = str_replace('.', '.children.', $item['path']);

                if (Arr::has($items, $key)) {
                    continue;
                }

                // Ensure the children array existence.
                else if (! isset($item['children'])) {
                    $item['children'] = array();
                }

                // Check if the user is allowed to use this menu item.
                if (! $this->userIsAllowed($item, $user, $gate)) {
                    continue;
                }

                // Add the item to the menu items array.
                Arr::set($items, $key, $item);

                //
                $itemUrl = $item['url'];

                if (! empty($path) || ($itemUrl === '#')) {
                    continue;
                }

                $pattern = preg_quote($itemUrl, '#');

                if (($itemUrl == $url) || (preg_match('#^' .$pattern .'/page/[0-9]+$#', $url) === 1)) {
                    $path = $item['path'];
                }
            }
        }

        return $this->prepareItems($items, $path, $url);
    }

    /**
     * Determine if the menu item usage is allowed by the specified User Roles.
     *
     * @param  array  $item
     * @param  mixed  $user
     * @param  \Nova\Auth\Access\GateInterface  $gate
     * @return boolean
     */
    protected function userIsAllowed(array $item, UserInterface $user, GateInterface $gate)
    {
        if (isset($item['role']) && ($item['role'] !== 'any')) {
            if (is_string($roles = $item['role'])) {
                $roles = explode(',', $roles);
            }

            if (! $user->hasRole($roles)) {
                return false;
            }
        }

        if (! isset($item['can'])) {
            return true;
        }

        // The abilities entry was specified.
        else if (! is_array($abilities = $item['can'])) {
            $abilities = explode('|', $abilities);
        }

        foreach ($abilities as $value) {
            if (! is_array($value)) {
                list ($ability, $parameters) = array_pad(explode(':', $value, 2), 2, array());

                if (is_string($parameters)) {
                    $parameters = explode(',', $parameters);
                }

            // The information is given as an array.
            } else if (! is_null($ability = Arr::get($value, 'ability'))) {
                $parameters = Arr::get($value, 'arguments', array());

                if (! is_array($parameters)) {
                    $parameters = array($parameters);
                }
            }

            // Invalid ability format specified.
            else {
                throw new LogicException('Invalid user ability.');
            }

            if (call_user_func(array($gate, 'allows'), $ability, $parameters)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Prepare the given menu items.
     *
     * @param  array  $items
     * @param  string $path
     * @param  string $url
     * @param  string $pageName
     * @return array
     */
    protected function prepareItems(array $items, $path, $url)
    {
        foreach ($items as &$item) {
            $itemUrl = $item['url'];

            //
            $pattern = preg_quote($itemUrl, '#');

            if (($itemUrl == $url) || Str::startsWith($path, $item['path'])) {
                $active = true;
            } else if (($itemUrl !== '#') && (preg_match('#^' .$pattern .'/page/[0-9]+$#', $url) === 1)) {
                $active = true;
            } else {
                $active = false;
            }

            $item['active'] = $active;

            if (! empty($children = $item['children'])) {
                $item['children'] = $this->prepareItems($children, $path, $url);
            }
        }

        // Sort the menu items by their weight and title.
        usort($items, function ($a, $b)
        {
            if ($a['weight'] === $b['weight']) {
                return strcmp($a['title'], $b['title']);
            }

            return ($a['weight'] < $b['weight']) ? -1 : 1;
        });

        return $items;
    }
}
