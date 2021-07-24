<template>
  <div>
    <Navbar />
    <modale v-bind:revele="revele" v-bind:toggleModale="toggleModale"></modale>
    <div class="button-position">
      <div v-on:click="toggleModale" class="btn btn-danger">
        <font-awesome-icon :icon="['fas', 'plus-circle']" class="icon" />
        Ajouter une vidéo
      </div>
    </div>
    <div>
      <CardVideo />
    </div>
  </div>
</template>

<script>
import axios from 'axios'
import Modale from '../components/ModaleUploadVideo.vue'

export default {
  components: {
    modale: Modale,
  },
  data() {
    return {
      revele: false,
    }
  },
  mounted() {
    this.token = localStorage.token
    this.userid = localStorage.userid
  },

  methods: {
    toggleModale: function () {
      this.revele = !this.revele
    },
  },
}
</script>


<style scoped>
body {
  margin: 0;
  background-color: #e9e9e9;
  font-family: Arial, Helvetica, sans-serif;
}
.button-position {
  display: flex;
  justify-content: center;
  margin: 20px;
}
.submit {
  cursor: pointer;
  border-radius: 5em;
  color: #fff;
  background: linear-gradient(to right, #0088a9, #6ac3d9);
  border: 0;
  width: 40%;
  padding-left: 15px;
  padding-right: 15px;
  padding-bottom: 10px;
  padding-top: 10px;
  font-family: 'Montserrat', sans-serif;

  font-size: 13px;
  box-shadow: 0 0 20px 1px rgba(0, 0, 0, 0.04);
}
</style>
