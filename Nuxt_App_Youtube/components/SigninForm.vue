<template>
  <div class="main">
    <p class="sign" align="center">Se connecter</p>
    <form class="form1" @submit.prevent="handleSubmit">
      <input
        class="username"
        type="text"
        align="center"
        v-model="pseudo"
        placeholder="Pseudo"
      />
      <input
        class="pass"
        type="password"
        align="center"
        v-model="password"
        placeholder="Password"
      />
      <button type="submit" class="submit" align="center">Se connecter</button>
      <p class="forgot" align="center"><a href="#">Mot de passe oublié?</a></p>
      <p class="create" align="center"><a href="/signup">Créer un compte</a></p>
    </form>
  </div>
</template>

<script>
import axios from 'axios'
export default {
  name: 'Signin',
  data() {
    return {
      pseudo: '',
      password: '',
    }
  },
  methods: {
    async handleSubmit() {
      const data = {
        pseudo: this.pseudo,
        password: this.password,
      }
      const response = await axios.post(
        'http://localhost:80/myYoutube/auth',
        data,
        {
          headers: {
            'Content-Type': 'application/json',
          },
        }
      )
      localStorage.setItem('token', response.data.data.access_token)
      localStorage.setItem('userid', response.data.data.user_id)
      this.$router.push('/')
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
  height: 450px;
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

.username {
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

.pass {
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

.username:focus,
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

.forgot {
  padding-top: 15px;
  margin-top: 20px;
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
