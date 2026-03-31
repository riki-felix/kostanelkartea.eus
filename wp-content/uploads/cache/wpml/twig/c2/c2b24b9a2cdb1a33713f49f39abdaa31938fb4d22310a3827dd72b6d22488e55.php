<?php

namespace WPML\Core;

use \WPML\Core\Twig\Environment;
use \WPML\Core\Twig\Error\LoaderError;
use \WPML\Core\Twig\Error\RuntimeError;
use \WPML\Core\Twig\Markup;
use \WPML\Core\Twig\Sandbox\SecurityError;
use \WPML\Core\Twig\Sandbox\SecurityNotAllowedTagError;
use \WPML\Core\Twig\Sandbox\SecurityNotAllowedFilterError;
use \WPML\Core\Twig\Sandbox\SecurityNotAllowedFunctionError;
use \WPML\Core\Twig\Source;
use \WPML\Core\Twig\Template;

/* template.twig */
class __TwigTemplate_1a75b35a18108cf1bd4d9f76cbdc4995157e4d984ab4e867f074d2c51234d529 extends \WPML\Core\Twig\Template
{
    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->parent = false;

        $this->blocks = [
        ];
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        // line 1
        $context["css_classes_flag"] = \WPML\Core\twig_trim_filter(("wpml-ls-flag " . $this->getAttribute(($context["backward_compatibility"] ?? null), "css_classes_flag", [])));
        // line 2
        $context["css_classes_native"] = \WPML\Core\twig_trim_filter(("wpml-ls-native " . $this->getAttribute(($context["backward_compatibility"] ?? null), "css_classes_native", [])));
        // line 3
        $context["css_classes_display"] = \WPML\Core\twig_trim_filter(("wpml-ls-display " . $this->getAttribute(($context["backward_compatibility"] ?? null), "css_classes_display", [])));
        // line 4
        $context["css_classes_bracket"] = \WPML\Core\twig_trim_filter(("wpml-ls-bracket " . $this->getAttribute(($context["backward_compatibility"] ?? null), "css_classes_bracket", [])));
        // line 5
        $context["css_classes_link"] = \WPML\Core\twig_trim_filter(("wpml-ls-link " . $this->getAttribute($this->getAttribute(($context["language"] ?? null), "backward_compatibility", []), "css_classes_a", [])));
        // line 6
        echo "
<div role=\"navigation\" aria-label=\"";
        // line 7
        echo \WPML\Core\twig_escape_filter($this->env, ($context["ls_aria_label"] ?? null), "html", null, true);
        echo "\" class=\"";
        echo \WPML\Core\twig_escape_filter($this->env, ($context["css_classes"] ?? null), "html", null, true);
        echo " wpml-ls-legacy-list-horizontal\"";
        if ($this->getAttribute(($context["backward_compatibility"] ?? null), "css_id", [])) {
            echo " id=\"";
            echo \WPML\Core\twig_escape_filter($this->env, $this->getAttribute(($context["backward_compatibility"] ?? null), "css_id", []), "html", null, true);
            echo "\"";
        }
        echo ">
\t<ul>";
        // line 10
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(($context["languages"] ?? null));
        $context['loop'] = [
          'parent' => $context['_parent'],
          'index0' => 0,
          'index'  => 1,
          'first'  => true,
        ];
        if (is_array($context['_seq']) || (is_object($context['_seq']) && $context['_seq'] instanceof \Countable)) {
            $length = count($context['_seq']);
            $context['loop']['revindex0'] = $length - 1;
            $context['loop']['revindex'] = $length;
            $context['loop']['length'] = $length;
            $context['loop']['last'] = 1 === $length;
        }
        foreach ($context['_seq'] as $context["code"] => $context["language"]) {
            // line 11
            echo "<li class=\"";
            echo \WPML\Core\twig_escape_filter($this->env, $this->getAttribute($context["language"], "css_classes", []), "html", null, true);
            echo " wpml-ls-item-legacy-list-horizontal\">
\t\t\t\t<a href=\"";
            // line 12
            echo \WPML\Core\twig_escape_filter($this->env, $this->getAttribute($context["language"], "url", []), "html", null, true);
            echo "\" hreflang=\"";
            echo \WPML\Core\twig_escape_filter($this->env, $context["code"], "html", null, true);
            echo "\" lang=\"";
            echo \WPML\Core\twig_escape_filter($this->env, $context["code"], "html", null, true);
            echo "\" class=\"";
            echo \WPML\Core\twig_escape_filter($this->env, ($context["css_classes_link"] ?? null), "html", null, true);
            echo "\"";
            if ($this->getAttribute($context["language"], "is_current", [])) {
                echo " aria-current=\"page\"";
            }
            echo " aria-label=\"";
            echo \WPML\Core\twig_escape_filter($this->env, $this->getAttribute($context["language"], "menu_item_label", []), "html", null, true);
            echo "\" title=\"";
            echo \WPML\Core\twig_escape_filter($this->env, $this->getAttribute($context["language"], "menu_item_label", []), "html", null, true);
            echo "\">
\t\t\t\t\t";
            // line 13
            $this->loadTemplate("flag.twig", "template.twig", 13)->display($context);
            // line 15
            if (($this->getAttribute($context["language"], "is_current", []) && ($this->getAttribute($context["language"], "native_name", []) || $this->getAttribute($context["language"], "display_name", [])))) {
                // line 17
                $context["current_language_name"] = (($this->getAttribute($context["language"], "native_name", [], "any", true, true)) ? (\WPML\Core\_twig_default_filter($this->getAttribute($context["language"], "native_name", []), $this->getAttribute($context["language"], "display_name", []))) : ($this->getAttribute($context["language"], "display_name", [])));
                // line 18
                echo "<span class=\"";
                echo \WPML\Core\twig_escape_filter($this->env, ($context["css_classes_native"] ?? null), "html", null, true);
                echo "\">";
                echo \WPML\Core\twig_escape_filter($this->env, ($context["current_language_name"] ?? null), "html", null, true);
                echo "</span>";
            } else {
                // line 22
                if ($this->getAttribute($context["language"], "native_name", [])) {
                    // line 23
                    echo "<span class=\"";
                    echo \WPML\Core\twig_escape_filter($this->env, ($context["css_classes_native"] ?? null), "html", null, true);
                    echo "\">";
                    echo \WPML\Core\twig_escape_filter($this->env, $this->getAttribute($context["language"], "native_name", []), "html", null, true);
                    echo "</span>";
                }
                // line 26
                if (($this->getAttribute($context["language"], "display_name", []) && ($this->getAttribute($context["language"], "display_name", []) != $this->getAttribute($context["language"], "native_name", [])))) {
                    // line 27
                    echo "<span class=\"";
                    echo \WPML\Core\twig_escape_filter($this->env, ($context["css_classes_display"] ?? null), "html", null, true);
                    echo "\">";
                    // line 28
                    if ($this->getAttribute($context["language"], "native_name", [])) {
                        echo "<span class=\"";
                        echo \WPML\Core\twig_escape_filter($this->env, ($context["css_classes_bracket"] ?? null), "html", null, true);
                        echo "\"> (</span>";
                    }
                    // line 29
                    echo \WPML\Core\twig_escape_filter($this->env, $this->getAttribute($context["language"], "display_name", []), "html", null, true);
                    // line 30
                    if ($this->getAttribute($context["language"], "native_name", [])) {
                        echo "<span class=\"";
                        echo \WPML\Core\twig_escape_filter($this->env, ($context["css_classes_bracket"] ?? null), "html", null, true);
                        echo "\">)</span>";
                    }
                    // line 31
                    echo "</span>";
                }
            }
            // line 35
            echo "</a>
\t\t\t</li>";
            ++$context['loop']['index0'];
            ++$context['loop']['index'];
            $context['loop']['first'] = false;
            if (isset($context['loop']['length'])) {
                --$context['loop']['revindex0'];
                --$context['loop']['revindex'];
                $context['loop']['last'] = 0 === $context['loop']['revindex0'];
            }
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['code'], $context['language'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 39
        echo "</ul>
</div>
";
    }

    public function getTemplateName()
    {
        return "template.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  158 => 39,  143 => 35,  139 => 31,  133 => 30,  131 => 29,  125 => 28,  121 => 27,  119 => 26,  112 => 23,  110 => 22,  103 => 18,  101 => 17,  99 => 15,  97 => 13,  79 => 12,  74 => 11,  57 => 10,  45 => 7,  42 => 6,  40 => 5,  38 => 4,  36 => 3,  34 => 2,  32 => 1,);
    }

    /** @deprecated since 1.27 (to be removed in 2.0). Use getSourceContext() instead */
    public function getSource()
    {
        @trigger_error('The '.__METHOD__.' method is deprecated since version 1.27 and will be removed in 2.0. Use getSourceContext() instead.', E_USER_DEPRECATED);

        return $this->getSourceContext()->getCode();
    }

    public function getSourceContext()
    {
        return new Source("{% set css_classes_flag    = ('wpml-ls-flag ' ~ backward_compatibility.css_classes_flag)|trim %}
{% set css_classes_native  = ('wpml-ls-native ' ~ backward_compatibility.css_classes_native)|trim %}
{% set css_classes_display = ('wpml-ls-display ' ~ backward_compatibility.css_classes_display)|trim %}
{% set css_classes_bracket = ('wpml-ls-bracket ' ~ backward_compatibility.css_classes_bracket)|trim %}
{% set css_classes_link    = ('wpml-ls-link ' ~ language.backward_compatibility.css_classes_a)|trim %}

<div role=\"navigation\" aria-label=\"{{ ls_aria_label }}\" class=\"{{ css_classes }} wpml-ls-legacy-list-horizontal\"{% if backward_compatibility.css_id %} id=\"{{ backward_compatibility.css_id }}\"{% endif %}>
\t<ul>

\t\t{%- for code, language in languages -%}
\t\t\t<li class=\"{{ language.css_classes }} wpml-ls-item-legacy-list-horizontal\">
\t\t\t\t<a href=\"{{ language.url }}\" hreflang=\"{{ code }}\" lang=\"{{ code }}\" class=\"{{ css_classes_link }}\"{% if language.is_current %} aria-current=\"page\"{% endif %} aria-label=\"{{ language.menu_item_label }}\" title=\"{{ language.menu_item_label }}\">
\t\t\t\t\t{% include 'flag.twig' %}

\t\t\t\t\t{%- if language.is_current and (language.native_name or language.display_name)  -%}

\t\t\t\t\t\t{%- set current_language_name = language.native_name|default(language.display_name) -%}
\t\t\t\t\t\t<span class=\"{{ css_classes_native }}\">{{- current_language_name -}}</span>

\t\t\t\t\t{%- else -%}

\t\t\t\t\t\t{%- if language.native_name -%}
\t\t\t\t\t\t\t<span class=\"{{ css_classes_native }}\">{{- language.native_name -}}</span>
\t\t\t\t\t\t{%- endif -%}

\t\t\t\t\t\t{%- if language.display_name and (language.display_name != language.native_name) -%}
\t\t\t\t\t\t\t<span class=\"{{ css_classes_display }}\">
\t\t\t\t\t\t\t{%- if language.native_name -%}<span class=\"{{ css_classes_bracket }}\"> (</span>{%- endif -%}
\t\t\t\t\t\t\t\t{{- language.display_name -}}
\t\t\t\t\t\t\t{%- if language.native_name -%}<span class=\"{{ css_classes_bracket }}\">)</span>{%- endif -%}
\t\t\t\t\t\t\t</span>
\t\t\t\t\t\t{%- endif -%}

\t\t\t\t\t{%- endif -%}
\t\t\t\t</a>
\t\t\t</li>
\t\t{%- endfor -%}

\t</ul>
</div>
", "template.twig", "/www/kinsta/public/kostan-elkartea/wp-content/plugins/sitepress-multilingual-cms/templates/language-switchers/legacy-list-horizontal/template.twig");
    }
}
