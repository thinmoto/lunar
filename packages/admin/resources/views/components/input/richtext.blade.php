<div
    x-ref="input"
    x-data="{
        value: @entangle($attributes->wire('model')),
        init() {
        let options = {{ json_encode($options) }};

                  const fullToolbarOptions = [
                  [{ header: [1, 2, 3, false] }],
                  ['bold', 'italic', 'underline'],  [{ 'align': [] }], ['link'], [{ 'color': [] }, { 'background': [] }],
                [{ 'list': 'ordered'}, { 'list': 'bullet' }, { 'list': 'check' }],[{ 'indent': '-1'}, { 'indent': '+1' }],
                ['clean']
          ];

          options.modules = {
              toolbar: {
                  container: fullToolbarOptions
              }
          };

          const {{ $instanceId }} = new Quill($refs.editor, options)

          {{ $instanceId }}.on('text-change', () => {
            $dispatch('quill-input', {{ $instanceId }}.root.innerHTML)
          })
        }
    }"
    x-on:quill-input="value = $event.detail"
    wire:ignore
>
  <div>
      <div x-ref="editor">{!! $initialValue !!}</div>
  </div>
</div>
