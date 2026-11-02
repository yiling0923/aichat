@php($ann = (array) \App\Models\PlatformSetting::get('announcement', []))
@if (! empty($ann['text']) && ! session('support'))
<div class="announce announce-{{ $ann['tone'] ?? 'info' }}" id="announce" role="status" data-key="{{ md5($ann['text'].($ann['at'] ?? '')) }}" hidden>
    <span>{{ $ann['text'] }}</span>
    <button type="button" class="announce-x" aria-label="{{ __('Close') }}">&times;</button>
</div>
<script>
(function () { var el = document.getElementById('announce'), k = 'ann-' + el.dataset.key, gone = false;
  try { gone = localStorage.getItem(k) === '1'; } catch (e) {}
  if (!gone) { el.hidden = false; }
  el.querySelector('.announce-x').addEventListener('click', function () { el.hidden = true; try { localStorage.setItem(k, '1'); } catch (e) {} });
})();
</script>
@endif
