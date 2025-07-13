    document.addEventListener('DOMContentLoaded', function () {
      document.querySelectorAll('.test-xpath-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
          e.preventDefault();
          e.stopPropagation();
    
          const buttons = document.querySelectorAll('.test-xpath-btn');
          if (btn.disabled) return;
    
          const label = btn.textContent.toLowerCase();
          let field = 'src_xpath_body';
    
          if (label.includes('imagem')) field = 'src_xpath_img';
          else if (label.includes('título')) field = 'src_xpath_title';
          else if (label.includes('link')) field = 'src_xpath_link';
    
          const url = document.querySelector('[name="src_url"]')?.value?.trim();
          const xpath = document.querySelector('[name="' + field + '"]')?.value?.trim();
    
          if (!url || !xpath) {
            alert("Por favor, preencha a URL e o XPath antes de testar.");
            return;
          }
    
          let modalUrl = 'admin_config.php?ajax_xpath=1&url=' + encodeURIComponent(url) +
            '&field=' + encodeURIComponent(field) +
            '&xpath=' + encodeURIComponent(xpath);
    
          if (field !== 'src_xpath_link') {
            const linkXPath = document.querySelector('[name="src_xpath_link"]')?.value?.trim();
            if (linkXPath) {
              modalUrl += '&link=' + encodeURIComponent(linkXPath);
            }
          }
    
          // Desativa todos os botões e mostra spinner no clicado
          buttons.forEach(b => b.disabled = true);
          const originalText = btn.innerHTML;
          btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Testando...';
    
          fetch(modalUrl)
            .then(response => response.text())
            .then(html => {
              const modal = document.getElementById('uiModal');
              if (!modal) return;
    
              modal.querySelector('.modal-caption').innerText = 'Resultado XPath: ' + field;
              modal.querySelector('.modal-body').innerHTML = html;
              $('#uiModal').modal('show');
            })
            .catch(err => {
              alert("Erro ao carregar pré-visualização.\\n\\n" + err);
            })
            .finally(() => {
              buttons.forEach(b => b.disabled = false);
              btn.innerHTML = originalText;
            });
        });
      });
    });

