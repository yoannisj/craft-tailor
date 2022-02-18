# Tailor Classnames

Tailor provides twig functions to work with HTML classnames and transfer them across our modular component files.

# Defining a classnames tree

To define the classnames to use in your current twig file, simply set a `classnames` variable and use the structure below:

```twig
{% set classnames = {
  'root': 'component',
  'wrap': ['component-wrap', 'l-wrap'],
  'list': {
    'root': 'component-list',
    'item': {
      'root': 'component-item',
      'link': 'component-item-link link',
    },
  }
} %}
```

Each key corresponds to an node (i.e. an HTML element) in your component on which you might want to add dynamic classnames (see below).

If the key contains a [hash](https://craftcms.com/docs/3.x/dev/twig-primer.html#hashes) value, then it defines a sub-tree, which can have it's own nodes.

# Outputting classnames

You can use the classnames defined in your classnames tree with the `classnames()` function.

```twig
<div class="text-lg {{ classnames('root') }}">
    <div class="{{ classnames('wrap') }}">
        <div class="{{ classnames('inner') }}">
            <ul class="uilist {{ classnames('list') }}">
                {% for item in items %}
                    <li class="{{ classnames('list.item') }}">
                        <a class="{{ classnames('list.item.link') }}"
                            href="{{ item.url }}"
                        >{{ item.label }}</a>
                    </li>
                {% endfor %}
            </ul>
        </div>
    </div>
</div>
```

The twig template above will produce the following HTML:

```html
<div class="text-lg component">
    <div class="component-wrap l-wrap">
        <div class="">
            <ul class="uilist component-list">
                <li class="component-item">
                    <a class="component-item-link link"
                        href="/items/first"
                    >First Item</a>
                </li>
                <li class="component-item">
                    <a class="component-item-link link"
                        href="/items/second"
                    >Second Item</a>
                </li>
                <!-- ... -->
            </ul>
        </div>
    </div>
</div>
```

Notice that the `'inner'` key was not defined in the `classnames` variable, and so a blank string was output.

Notice that the `classnames()` supports dot-notation to extract values from nodes inside a sub-tree.

# Accessing the parsed classnames tree

Behind the scenes, Tailor will parse the `classnames` variable and structure it in order to help other helper functions such as `classnames()` to work with the tree.

You can see access the parsed classnames tree with the `getClassnames()` function:

```twig
{% set parsedClassnames = getClassnames() %}
```

If you dump the returned value, you will see that it looks like this: 

```twig
{
    'root': 'component',
    'wrap': {
        'root': ['component-wrap', 'l-wrap'],
    },
    'list': {
        'root': 'component-list',
        'item': {
            'root': 'component-item',
            'link': {
                'root': ['component-item-link', 'link'],
            },
        },
    }
}
```

Notice how values which contain multiple class names are transformed to arrays, and every key unfolded into a sub-tree with at least one `'root'` key.

## Accessing sub-trees

If you give the `getClassnames()` method a string, it will return the corresponding parsed sub-tree.

```twig
{% set listClassnames = getClassnames('list') %}

{
    'root': 'component-list',
    'item': {
        'root': 'component-item',
        'link': {
            'root': ['component-item-link', 'link'],
        },
    }
}
```

This is useful if you want to pass a sub-tree to a Twig partial:

```twig
{# _components/component/index.twig #}
{% include '_components/component/list' with {
    classnames: getClassnames('list'),
} %}

{# _components/component/list.twig #}
<ul class="uilist {{ classnames('root') }}">
    {% for item in items %}
        <li class="{{ classnames('item') }}">
            <a class="{{ classnames('item.link') }}"
                href="{{ item.url }}"
            >{{ item.label }}</a>
        </li>
    {% endfor %}
</ul>
```

Notice how inside the list partial, the paths given to the `classnames()` function are now relative to the 'list' sub-tree. That is because we have set the `classnames` variable to that sub-tree (when we included the partial).

# Modifying the classnames tree

Dynamic classnames are kind of useless if you can not modify them using your own logic in your templates.

Tailor only allows you to add classname values, not remove them. The idea is that components should be able to reliably pass down classname values  sub-components. In other words, partials should not mess with the classnames they receive from the parent template that includes them.

You can add values to your classnames tree with `addClassnames()`

```twig
{% if true %}
    {% do addClassnames({
        root: 'component--lg',
        inner: 'component-inner',
        list: 'component-list--alternate'
    }) %}
{% endif %}
```

The `addClassnames()` function modifies the `classnames` variable in the current context. The parsed classnames tree now looks like this (the order of keys might differ):

```twig
{# getClassnames() #}
{
    root: ['component, 'component--lg'],
    wrap: {
        root: ['component-wrap', 'l-wrap' ],
    },
    inner: {
        root: 'component-inner',
    },
    list: {
        root: ['component-list', 'component-list--alternate'],
        item: {
            root: 'component-item',
            link: {
                root: [ 'component-link', 'link' ],
            },
        }
    }
}
```

Notice how `addClassnames()` automatically moves the value added to the `'list'` node inside it's sub-tree's `'root'` key.

## Modifying sub-trees

If you give the `addClass()` function a string as first argument, you can also add values to select sub-keys:

```twig
{% if true %}
    {% do addClassnames('wrap', 'l-wrap--wide') %}
{% endif %}

{# ... #}

<div class="{{ classnames('wrap') }}"></div>
{# <div class="component-wrap l-wrap l-wrap--wide"></div> #}
```

Or you can extend select sub-trees:

```twig
{% if true %}
    {% do addClassnames('list', {
        'item': 'component-item--lg',
    }) %}
{% endif %}

{# ... #}

<li class="{{ classnames('list.item') }}"></li>
{# <li class="component-item component-item--lg"></li> #}
```

And give it nested keys:

```twig

{% if true %}
    {% do addClassnames('list', {
        'item': {
            root: 'flex items-center',
            link: 'h-4',
        },
    }) %}
{% endif %}

{# ... #}

<li class="{{ classnames('list.item') }}">
    <a class="{{ classnames('list.item.link') }}"></a>
</li>

{#
    <li class="component-item component-item--lg flex items-center">
        <a class="component-link link h-4"></a>
    </li>
#}
```

## Composing classname trees

The `composeClassnames()` function does the same as `addClassnames()`, with the key difference that it does **NOT**  modify the classnames tree in the current context. Instead, it returns a copy what the modified classnames tree would be.

This is useful if you want to pass modified classnames to partials, but not affect other elements in your current template:

```twig
{% for item in items %}
    
    {% set itemClassnames = loop.last ? composeClassnames('item', 'is-last') : getClassnames('item') %}

    {% include 'components/component/item' with {
        url: item.url,
        label: item.label,
        classnames: itemClassnames,
    } %}

{% endfor %}
```

In this example, only the last item will receive the `'is-last'` class.

If you want to create a modified copy of the entire current classnames tree, you have to pass it in as first argument:

```twig
{% set rowClassnames = composeClassnames(classnames, {
    root: 'component--row',
    inner: 'component-inner--row',
    list: 'component-list--row',
}) %}
```

This still will not modify the current classnames tree.
