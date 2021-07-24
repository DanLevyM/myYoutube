<template>
  <div>
    <Navbar />

    <div class="title">
      <h1>Partagez et visionnez vos vidéos en toute liberté !</h1>
    </div>

    <Card />

    <div>

    </div>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  components: {},
  data() {
    return {
      token: '',
      userid: '',
    }
  },
  // method: {
  //   update() {
  //     axios
  //       .get('https://uselessfacts.jsph.pl/random.json?language=en')
  //       .then((reponse) => (this.phrase = reponse.data.text))
  //   },
  // },
  // mounted() {
  //   axios
  //     .get('https://uselessfacts.jsph.pl/random.json?language=en')
  //     .then((reponse) => (this.phrase = reponse.data.text))
  // },

  mounted() {
    if (localStorage.token) {
      this.token = localStorage.token
    }
    if (localStorage.userid) {
      this.userid = localStorage.userid
    }
  },
  async created() {
    const response = await axios.get('/', {
      headers: {
        'Content-Type': 'application/json',
        // Authorization: 'Bearer' + localStorage.getItem('token'),
        Authorization: 'Bearer' + this.token,
      },
    })

    console.log(response)
  },
}
</script>
<style scoped>
body {
  margin: 0;
  background-color: #e9e9e9;
  font-family: Arial, Helvetica, sans-serif;
}
.title {
  display: flex;
  justify-content: center;
  align-items: center;
  height: 300px;
  font-family: 'Montserrat', sans-serif;
}
</style>
