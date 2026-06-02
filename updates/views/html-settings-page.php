<div class="fa-licenses-container">
  <h2 class="licences-title">Fernando Acosta - Ativação de licenças!</h2>

  <?php if ( isset( $_GET['welcome'] ) ) { ?>
    <div class="welcome-message">
      <h3>Obrigado por instalar!</h3>
      <p>Agora você terá acesso a todos os recursos do plugin!</p>
      <p><strong>Ative sua licença</strong> abaixo para garantir que está usando uma versão autêntica e obter suporte e atualizações automáticas</p>

      <p>Em caso de dúvidas, entre em contato através do site oficial <a href="https://fernandoacosta.net" target="_blank">fernandoacosta.net</a></p>
    </div>
  <?php } elseif ( isset( $_GET['reminder'] ) ) { ?>
    <div class="welcome-message" style="padding: 30px 50px; margin-bottom: 50px; color: #721c24; background-color: #f8d7da; border: 2px solid #721c24;">
      <h3 style="color: #721c24;">Mantenha seu site seguro!</h3>
      <p>Ative suas licenças para ter acesso às versões mais recentes dos plugins com novos recursos e correções de segurança!</p>
      <p>A licença pode ser encontnrada através do site oficial <a href="https://fernandoacosta.net" target="_blank">fernandoacosta.net</a></p>
    </div>
  <?php } ?>

  <?php do_action( 'fa_admin_notices' ); ?>

  <h3 class="yourplugins">Seus plugins</h3>
  <p class="issues">Caso esteja com dificuldades na hora de ativar, <a href="https://fernandoacosta.net/license-help">clique aqui</a>.</p>

  <div class="plugins-activation headers">
    <div class="single-plugin-activation headers">
      <div class="plugin-title">Plugin</div>
      <div class="plugin-license">
        Licença
      </div>
      <div class="plugin-actions">
        Ações
      </div>
    </div>

    <?php foreach ( fa_updates()->get_plugins() as $plugin ) {
      $new_version = $this->get_plugin_new_version( $plugin['id'] );
    ?>
      <form class="single-plugin-activation <?php echo $plugin['key'] ? 'plugin-activated' : 'plugin-deactivated'; ?>" method="POST" action="">
        <?php wp_nonce_field( $plugin['key'] ? 'fa_deactivate_plugin' : 'fa_activate_plugin', '_fa_nonce', false ); ?>

        <input type="hidden" name="slug" value="<?php echo $plugin['slug']; ?>" />

        <div class="plugin-title">
          <div class="plugin-name">
            <?php echo $plugin['name']; ?>
          </div>

          <?php if ( $new_version ) {
            echo '<span class="new-version">Versão ' . $new_version['version'] . ' disponível!</span>';
          } ?>

        </div>
        <div class="plugin-license">
          <input name="license" type="text" required placeholder="Sua licença" <?php echo $plugin['key'] ? 'value="' . substr($plugin['key'], 0, 4) . '-****-****-' . substr($plugin['key'], -4) .'" disabled' : ''; ?> />
        </div>
        <div class="plugin-actions">
          <button value="save_license" class="activate button-primary"><?php echo $plugin['key'] ? 'Desativar licença' : 'Ativar licença'; ?></button>
        </div>
      </form>
    <?php } ?>




    <h3 class="yourplugins">Configure seus plugins</h3>
    <p class="issues">Abaixo, os links iniciais de configuração para cada plugin.</p>

    <ul style="list-style: circle; list-style-position: inside;">
      <?php foreach ( fa_updates()->get_plugins() as $plugin ) {
        if ( empty( $plugin['key'] ) ) {
          continue;
        }

        if ( empty( $plugin['setup_url'] ) ) {
          continue;
        }
        ?>
        <li style="margin-bottom: 15px;"><a href="<?php echo $plugin['setup_url']; ?>"><?php echo $plugin['name']; ?></a></li>
      <?php } ?>
    </ul>


    <?php if ( $this->feed ) {
      echo $this->feed['html_feed'];
    } ?>
  </div>
</div>
