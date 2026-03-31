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

/* dialog-box.twig */
class __TwigTemplate_ff26924b2c97b90bd14ae5781654de5335ae74e5f0fb370bfdd5196d88297078 extends \WPML\Core\Twig\Template
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
        echo "<div id=\"wpml-ls-dialog\" style=\"display:none;\" >
    <div class=\"js-wpml-ls-dialog-inner\">

    </div>
    <div class=\"wpml-dialog-footer \">
        <span class=\"errors icl_error_text\"></span>
        <input
            class=\"js-wpml-ls-dialog-close cancel wpml-dialog-close-button alignleft wpml-button base-btn gray-light-btn\"
            value=\"";
        // line 9
        echo \WPML\Core\twig_escape_filter($this->env, $this->getAttribute($this->getAttribute(($context["strings"] ?? null), "misc", []), "button_cancel", []), "html", null, true);
        echo "\"
            type=\"button\"
        >
        <input class=\"button-primary js-wpml-ls-dialog-save wpml-button base-btn term-save alignright\" value=\"";
        // line 12
        echo \WPML\Core\twig_escape_filter($this->env, $this->getAttribute($this->getAttribute(($context["strings"] ?? null), "misc", []), "button_save", []), "html", null, true);
        echo "\" type=\"submit\">
        <span class=\"spinner alignright\"></span>
    </div>
</div>
";
    }

    public function getTemplateName()
    {
        return "dialog-box.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  48 => 12,  42 => 9,  32 => 1,);
    }

    /** @deprecated since 1.27 (to be removed in 2.0). Use getSourceContext() instead */
    public function getSource()
    {
        @trigger_error('The '.__METHOD__.' method is deprecated since version 1.27 and will be removed in 2.0. Use getSourceContext() instead.', E_USER_DEPRECATED);

        return $this->getSourceContext()->getCode();
    }

    public function getSourceContext()
    {
        return new Source("<div id=\"wpml-ls-dialog\" style=\"display:none;\" >
    <div class=\"js-wpml-ls-dialog-inner\">

    </div>
    <div class=\"wpml-dialog-footer \">
        <span class=\"errors icl_error_text\"></span>
        <input
            class=\"js-wpml-ls-dialog-close cancel wpml-dialog-close-button alignleft wpml-button base-btn gray-light-btn\"
            value=\"{{ strings.misc.button_cancel }}\"
            type=\"button\"
        >
        <input class=\"button-primary js-wpml-ls-dialog-save wpml-button base-btn term-save alignright\" value=\"{{ strings.misc.button_save }}\" type=\"submit\">
        <span class=\"spinner alignright\"></span>
    </div>
</div>
", "dialog-box.twig", "/www/kinsta/public/kostan-elkartea/wp-content/plugins/sitepress-multilingual-cms/templates/language-switcher-admin-ui/dialog-box.twig");
    }
}
