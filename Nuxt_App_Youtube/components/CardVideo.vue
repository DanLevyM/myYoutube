<template>
  <div class="row row-video justify-content-center align-items-center">
    <div
      class="col-sm-6 col-xl-3 pos-video"
      v-for="video in videos"
      :key="video.filename"
    >
      <video width="420" height="340" controls>
        <source
          src="../../userVideos/user2/www.mp4"
          type="video/mp4"
        />
      </video>
      <div class="cnt-info">
        <label
          class="pol-title"
          v-for="videosTitle in video.filename.split('.').shift()"
          >{{ videosTitle }}</label>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'
export default {
  data() {
    return {
      url: '',
      token: '',
      userid: '',
      videos: [],
    }
  },
  mounted() {
    if (localStorage.token) {
      this.token = localStorage.token
    }
    if (localStorage.userid) {
      this.userid = localStorage.userid
    }
    axios
      .get(`http://localhost:80/myYoutube/user/${this.userid}/videos`, {
        headers: {
          'Content-Type': 'application/json',
          Authorization: 'Bearer' + this.token,
        },
      })
      .then((reponse) => {
        this.videos = reponse.data.data.videos
        this.url = '../../userVideos/user' + this.userid + '/' + this.videos[0].filename
        // '../../userVideos/user15/Salut.mp4'

        console.log("URL : ", this.url)
      })
      .catch((e) => {
        console.log(e)
      })
  },
}
</script>

<style scoped>
.row-video {
  margin: 10px;
}
.pos-video {
  /* background: green; */
  display: flex;
  flex-direction: column;
  align-items: center;
}
.cnt-info {
  background: rgba(240, 240, 240, 0.5);
  width: 420px;
  padding: 15px 0;
}
.pol-title {
  font-size: 16px;
  font-family: 'Montserrat', sans-serif;
}
</style>
