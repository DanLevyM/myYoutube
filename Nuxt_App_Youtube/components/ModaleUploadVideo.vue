<template>
  <div class="bloc-modale" v-if="revele">
    <div class="overlay" v-on:click="toggleModale"></div>
    <div class="modale card">
      <div class="btn-modale btn btn-danger" v-on:click="toggleModale">
        <font-awesome-icon :icon="['fas', 'times']" class="icon" />
      </div>
      <div>
        <form action="" class="form" enctype="multipart/form-data">
          <div class="mt-4">
            <label for="uploadVideo">Nom du fichier :</label>
            <input
              type="text"
              id="videoTitle"
              name="videoTitle"
              v-model="videoTitle"
            />
          </div>
          <div class="mt-2">
            <label>Fichier à télécharger :</label>
            <input
              type="file"
              id="uploadVideo"
              name="uploadVideo"
              accept="video/mp4,video/x-m4v,video/*"
              @change="myfct($event)"
            />
          </div>
          <div class="mt-2">
            <input type="submit" value="Envoyer" v-on:click="getVideoInfo()" />
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
<script>
import axios from 'axios'

export default {
  name: 'Modale',
  props: ['revele', 'toggleModale'],
  data() {
    return {
      // videofile: 'D:/Bureau/api_rest/video1.mp4',
      videofile: null,
      attributes: null,
      token: '',
      userid: null,
      videoTitle: '',
    }
  },
  mounted() {
    this.token = localStorage.token
    this.userid = localStorage.userid
  },

  methods: {
    getVideoInfo() {
      this.attributes = `{"title":"title1", "filename":"${this.videoTitle}"}`
      console.log(document.getElementById('uploadVideo').files[0])
      let self = this
      self.video = document.getElementById('uploadVideo').files[0].name
      self.size = document.getElementById('uploadVideo').files[0].size

      var formData = new FormData()
      var file = document.getElementById('uploadVideo').files[0]

      formData.append('videofile', file)
      formData.append('attributes', this.attributes)
      console.log('form data here !!', formData)

      axios
        .post(
          `http://localhost:80/myYoutube/users/${this.userid}/videos`,
          formData,
          {
            headers: {
              'Content-Type': 'multipart/form-data',
              Authorization: `${this.token}`,
            },
          }
        )
        .then(function (response) {
          console.log(response)
        })
        .catch(function () {
          console.log('Fail')
        })
    },
    myfct($e) {
      this.videofile = $e.target.files[0]
      // console.log($e.target.files[0])
    },
  },
}
</script>
<style scoped>
.bloc-modale {
  position: fixed;
  z-index: 1;
  top: 0;
  bottom: 0;
  left: 0;
  right: 0;
  display: flex;
  justify-content: center;
  align-items: center;
}
.overlay {
  background: rgba(0, 0, 0, 0.5);
  position: fixed;
  top: 0;
  bottom: 0;
  left: 0;
  right: 0;
}
.modale {
  background: #ffffff;
  color: #333;
  padding: 50px;
  position: fixed;
  top: 30%;
}
.btn-modale {
  position: absolute;
  top: 10px;
  right: 10px;
}
</style>