(function (Drupal, once) {
  Drupal.behaviors.scientifictableFileButton = {
    attach(context) {
      once('scientifictableFileButton', '.xlsx-hidden-input', context).forEach((wrap) => {
        const input = wrap.querySelector('input[type="file"]');
        if (!input) return;

        // Assicura un id (serve per il "for" del label)
        if (!input.id) {
          input.id = 'xlsx-file-' + Math.random().toString(36).slice(2);
        }

        // Se già creato, evita doppioni
        if (wrap.querySelector('.xlsx-upload-ui')) return;

        const ui = document.createElement('div');
        ui.className = 'xlsx-upload-ui';

        const label = document.createElement('label');
        label.className = 'xlsx-upload-label';
        label.setAttribute('for', input.id);
        label.textContent = 'Select XLSX file';

        const filename = document.createElement('span');
        filename.className = 'xlsx-filename';
        filename.textContent = 'No file selected';

        ui.appendChild(label);
        ui.appendChild(filename);

        // Inserisci UI prima dell'input
        input.parentNode.insertBefore(ui, input);

        // Aggiorna nome file quando selezionato
        input.addEventListener('change', () => {
          const name = input.files && input.files.length ? input.files[0].name : 'No file selected';
          filename.textContent = name;
        });
      });
    }
  };
})(Drupal, once);
//search filter
(function (Drupal, once) {
  Drupal.behaviors.xlsxTableSearch = {
    attach(context) {
      const inputs = once('xlsx-search', '.xlsx-search', context);

      inputs.forEach((input) => {
        const wrap = input.closest('.xlsx-table-wrap') || document;
        const table = wrap.querySelector('table.xlsx-data-table') || wrap.querySelector('table');
        if (!table) return;

        const tbody = table.querySelector('tbody');
        if (!tbody) return;

        const rows = Array.from(tbody.querySelectorAll('tr'));

        const applyFilter = () => {
          const q = (input.value || '').toLowerCase().trim();

          rows.forEach((tr) => {
            const text = tr.textContent.toLowerCase();
            tr.style.display = text.includes(q) ? '' : 'none';
          });
        };

        input.addEventListener('input', applyFilter);
      });
    },
  };
})(Drupal, once);
