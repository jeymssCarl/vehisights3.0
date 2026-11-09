function toMinutes(value) {
  if (!/^\d{2}:\d{2}$/.test(value || '')) return null;
  const [hour, minute] = value.split(':').map(Number);
  return (hour * 60) + minute;
}

function toValue(hour, minute, period) {
  let hour24 = hour % 12;
  if (period === 'PM') hour24 += 12;
  return `${String(hour24).padStart(2, '0')}:${String(minute).padStart(2, '0')}`;
}

function toParts(value) {
  const [hour24, minute] = value.split(':').map(Number);
  return { hour: hour24 % 12 || 12, minute, period: hour24 >= 12 ? 'PM' : 'AM' };
}

function displayValue(value) {
  if (!/^\d{2}:\d{2}$/.test(value || '')) return 'Select time';
  const parts = toParts(value);
  return `${String(parts.hour).padStart(2, '0')}:${String(parts.minute).padStart(2, '0')} ${parts.period}`;
}

export function setupScheduleTimePickers({ dateInput, startInput, endInput, getManilaNow, showNotice }) {
  let startPicker;
  let endPicker;

  function createPicker(root) {
    const type = root.dataset.pickerType;
    const input = root.querySelector('.time-picker-value');
    const trigger = root.querySelector('.time-picker-trigger');
    const display = root.querySelector('.time-picker-display');
    const hoursColumn = root.querySelector('.picker-hours');
    const minutesColumn = root.querySelector('.picker-minutes');
    const periodsColumn = root.querySelector('.picker-periods');

    function available(value) {
      const candidate = toMinutes(value);
      if (candidate === null) return false;

      if (type === 'start') {
        if (candidate >= 1439) return false;
        const now = getManilaNow();
        if (dateInput.value < now.date) return false;
        return dateInput.value !== now.date || value >= now.time;
      }

      const start = toMinutes(startInput.value);
      return start !== null && candidate > start;
    }

    function firstAvailable(predicate = () => true) {
      for (let total = 0; total < 1440; total += 1) {
        const value = `${String(Math.floor(total / 60)).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`;
        if (predicate(toParts(value)) && available(value)) return value;
      }
      return '';
    }

    function notifyUnavailable() {
      if (type === 'start') {
        showNotice('Past time not available', 'That start time has already passed. Select a current or future time.');
      } else {
        showNotice('Invalid end time', 'The end time must be later than the selected start time.');
      }
    }

    function addOption(column, label, isSelected, isAvailable, onSelect) {
      const button = document.createElement('button');
      button.type = 'button';
      button.textContent = label;
      button.className = `time-picker-option${isSelected ? ' selected' : ''}${isAvailable ? '' : ' unavailable'}`;
      button.setAttribute('aria-disabled', isAvailable ? 'false' : 'true');
      button.addEventListener('click', () => isAvailable ? onSelect() : notifyUnavailable());
      column.appendChild(button);
    }

    function setValue(value) {
      if (!value || !available(value)) {
        notifyUnavailable();
        return;
      }

      input.value = value;
      display.textContent = displayValue(value);

      if (type === 'start' && endPicker) {
        endPicker.ensureValid();
        startPicker?.render();
      } else {
        render();
      }
    }

    function render() {
      const scrollPositions = {
        hours: hoursColumn.scrollTop,
        minutes: minutesColumn.scrollTop,
        periods: periodsColumn.scrollTop,
      };
      const selected = toParts(input.value || firstAvailable() || '00:00');
      hoursColumn.replaceChildren();
      minutesColumn.replaceChildren();
      periodsColumn.replaceChildren();

      for (let hour = 1; hour <= 12; hour += 1) {
        const fallback = firstAvailable(parts => parts.hour === hour && parts.period === selected.period);
        addOption(hoursColumn, String(hour).padStart(2, '0'), selected.hour === hour, fallback !== '', () => {
          const preferred = toValue(hour, selected.minute, selected.period);
          setValue(available(preferred) ? preferred : fallback);
        });
      }

      for (let minute = 0; minute < 60; minute += 1) {
        const value = toValue(selected.hour, minute, selected.period);
        addOption(minutesColumn, String(minute).padStart(2, '0'), selected.minute === minute, available(value), () => setValue(value));
      }

      for (const period of ['AM', 'PM']) {
        const fallback = firstAvailable(parts => parts.period === period);
        addOption(periodsColumn, period, selected.period === period, fallback !== '', () => {
          const preferred = toValue(selected.hour, selected.minute, period);
          setValue(available(preferred) ? preferred : fallback);
        });
      }

      const restoreScroll = () => {
        hoursColumn.scrollTop = scrollPositions.hours;
        minutesColumn.scrollTop = scrollPositions.minutes;
        periodsColumn.scrollTop = scrollPositions.periods;
      };
      restoreScroll();
      requestAnimationFrame(restoreScroll);
    }

    function ensureValid() {
      if (!available(input.value)) input.value = firstAvailable();
      display.textContent = displayValue(input.value);
      render();
    }

    function open() {
      document.querySelectorAll('.custom-time-picker.open').forEach(item => {
        if (item !== root) {
          item.classList.remove('open');
          item.querySelector('.time-picker-trigger').setAttribute('aria-expanded', 'false');
        }
      });
      ensureValid();
      root.classList.add('open');
      trigger.setAttribute('aria-expanded', 'true');
      requestAnimationFrame(() => {
        root.querySelectorAll('.time-picker-option.selected').forEach(item => item.scrollIntoView({ block: 'center' }));
      });
    }

    function close() {
      root.classList.remove('open');
      trigger.setAttribute('aria-expanded', 'false');
    }

    trigger.addEventListener('click', () => root.classList.contains('open') ? close() : open());
    display.textContent = displayValue(input.value);
    render();
    return { close, ensureValid, render };
  }

  startPicker = createPicker(document.querySelector('[data-picker-type="start"]'));
  endPicker = createPicker(document.querySelector('[data-picker-type="end"]'));

  document.addEventListener('pointerdown', event => {
    if (event.target.closest('.schedule-modal-overlay')) return;
    if (!event.target.closest('.custom-time-picker')) {
      startPicker.close();
      endPicker.close();
    }
  });

  return {
    sync() {
      startPicker.ensureValid();
      endPicker.ensureValid();
    },
    close() {
      startPicker.close();
      endPicker.close();
    },
  };
}
