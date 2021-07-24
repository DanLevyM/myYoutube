<template>
  <div class="main">
    <p class="sign" align="center">S'inscrire</p>
    <form class="form1" @submit.prevent="handleSubmit">
      <input
        class="inputStyle"
        type="text"
        align="center"
        v-model="username"
        placeholder="Username"
      />
      <input
        class="inputStyle"
        type="text"
        align="center"
        v-model="pseudo"
        placeholder="Pseudo"
      />
      <input
        class="inputStyle"
        type="email"
        align="center"
        v-model="email"
        placeholder="Email"
        pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,4}$"
      />
      <input
        class="inputStyle"
        type="password"
        align="center"
        v-model="password"
        placeholder="Password"
      />
      <button type="submit" class="submit" align="center">S'inscrire</button>
    </form>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  data() {
    return {
      username: '',
      pseudo: '',
      email: '',
      password: '',
    }
  },
  methods: {
    async handleSubmit() {
      const data = {
        username: this.username,
        pseudo: this.pseudo,
        email: this.email,
        password: this.password,
      }

      const response = await axios.post(
        'http://localhost:80/myYoutube/user',
        data,
        {
          headers: {
            'Content-Type': 'application/json',
          },
        }
      )
      this.$router.push('/signin')
    },
  },
}
</script>

<style scoped>
body {
  background-color: #f3ebf6;
  font-family: 'Montserrat', sans-serif;
}

.main {
  background-color: #ffffff;
  width: 400px;
  height: 500px;
  margin: 7em auto;
  border-radius: 1.5em;
  box-shadow: 0px 11px 35px 2px rgba(0, 0, 0, 0.14);
}

.sign {
  padding-top: 40px;
  color: #0088a9;
  font-family: 'Montserrat', sans-serif;
  font-weight: bold;
  font-size: 26px;
  text-transform: uppercase;
}

.inputStyle {
  width: 76%;
  color: rgb(38, 50, 56);
  font-weight: 700;
  font-size: 14px;
  letter-spacing: 1px;
  background: rgba(136, 126, 126, 0.04);
  padding: 10px 20px;
  border: none;
  border-radius: 20px;
  outline: none;
  box-sizing: border-box;
  border: 2px solid rgba(0, 0, 0, 0.02);
  margin-bottom: 50px;
  text-align: center;
  margin-bottom: 27px;
  font-family: 'Montserrat', sans-serif;
}

form.form1 {
  padding-top: 40px;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.inputStyle:focus,
.pass:focus {
  border: 2px solid rgba(0, 0, 0, 0.18) !important;
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

a {
  color: #0c6175;
  text-decoration: none;
}

@media (max-width: 600px) {
  .main {
    border-radius: 0px;
  }
}
</style>
