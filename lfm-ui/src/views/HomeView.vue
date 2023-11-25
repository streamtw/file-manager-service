<script setup>
import { onMounted } from 'vue'

onMounted(() => {
  lfm('lfm', {prefix: route_prefix});
})

const route_prefix = 'http://localhost:8050/filemanager'

const lfm = function(id, options) {
  const button = document.getElementById(id);

  button.addEventListener('click', function () {
    // const route_prefix = (options && options.prefix) ? options.prefix : '/filemanager';
    const target_input = document.getElementById(button.getAttribute('data-input'));
    const target_preview = document.getElementById(button.getAttribute('data-preview'));

    window.open(route_prefix + '?type=' + options.type || 'file', 'FileManager', 'width=900,height=600');
    window.SetUrl = function (items) {
      const file_path = items.map(function (item) {
        return item.url;
      }).join(',');

      // set the value of the desired input to image url
      target_input.value = file_path;
      target_input.dispatchEvent(new Event('change'));

      // clear previous preview
      target_preview.innerHtml = '';

      // set or change the preview image src
      items.forEach(function (item) {
        let img = document.createElement('img')
        img.setAttribute('style', 'height: 5rem')
        img.setAttribute('src', item.thumb_url)
        target_preview.appendChild(img);
      });

      // trigger change event
      target_preview.dispatchEvent(new Event('change'));
    };
  });
};


</script>

<template>
  <main>
    <h2 class="mt-4">Standalone Button</h2>
    <div class="input-group">
      <span class="input-group-btn">
        <a id="lfm" data-input="thumbnail" data-preview="holder" class="btn btn-primary text-white">
          <i class="fa fa-picture-o"></i> Choose
        </a>
      </span>
      <input id="thumbnail" class="form-control" type="text" name="filepath">
    </div>
  </main>
</template>
