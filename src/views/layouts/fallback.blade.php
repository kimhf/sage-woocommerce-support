<!doctype html>
<html {!! get_language_attributes() !!}>
  @include(\Kimhf\SageWoocommerceSupport\get_blade_template('partials/head'))
  <body @php body_class() @endphp>
    @php do_action('get_header') @endphp
    @include(\Kimhf\SageWoocommerceSupport\get_blade_template('partials/header'))
    <div class="wrap container" role="document">
      <div class="content">
        <main class="main">
          @yield('content')
        </main>
      </div>
    </div>
    @php do_action('get_footer') @endphp
    @include(\Kimhf\SageWoocommerceSupport\get_blade_template('partials/footer'))
    @php wp_footer() @endphp
  </body>
</html>
