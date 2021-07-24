<template>
  <nav>
    <a class="titleApp" href="/">MyYoutube</a>

    <form action="#" class="searchContainer">
      <input type="text" placeholder="Rechercher" name="search2" />

      <button class="iconbutton" type="submit">
        <font-awesome-icon :icon="['fas', 'search']" class="icon" />
      </button>
    </form>
    <div>
      <a href="/channel" class="cta" v-if="token"> Ma chaîne </a>
      <a href="/signin" class="cta" v-if="!token"> Se connecter </a>
      <a href="/logout" class="cta" v-else> Se déconnecter </a>
      <!-- <a href="#" v-if="user" class="cta">Log Out</a> -->
      <!-- <p v-if="user">{{ user.data.data.access_token }}</p> -->
    </div>
  </nav>
</template>

<script>
export default {
  data() {
    return {
      token: null,
      userid: null,
    }
  },
  mounted() {
    if (localStorage.token) {
      this.token = localStorage.token
    }
    if (localStorage.userid) {
      this.userid = localStorage.userid
    }
  },
  methods: {},
}
</script>

<style scoped>
.searchContainer {
  display: flex;
  height: 40px;
}
input {
  background-color: #ffffff;
  border-top-left-radius: 5px;
  border-bottom-left-radius: 5px;
  border: grey solid 1.5px;
  padding: 8px;
  margin: 0;
}
.iconbutton {
  width: 50px;
  border: grey solid 1.5px;
  background-color: rgba(196, 196, 192, 1);
  border-top-right-radius: 5px;
  border-bottom-right-radius: 5px;
}
.iconbutton:hover {
  background-color: rgba(196, 196, 192, 0.8);
}
.icon {
  color: rgba(108, 108, 108, 1);
}
.icon:hover {
  color: #24252a;
}

a {
  font-family: 'Montserrat', sans-serif;
  font-weight: 500;
  font-size: 16px;
  color: #edf0f1;
  text-decoration: none;
}

nav {
  font-family: 'Montserrat';
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 30px 10%;
  background-color: #24252a;
}

.titleApp {
  /* text-transform: uppercase; */
  font-family: 'Montserrat', sans-serif;
  color: #edf0f1;
  transition: all 0.3s ease 0s;
  cursor: pointer;
}

.titleApp:hover {
  color: #0088a9;
}

.cta {
  padding: 9px 25px;
  margin: 5px;
  background-color: rgba(0, 136, 169, 1);
  border: none;
  border-radius: 50px;
  cursor: pointer;
  transition: all 0.3s ease 0s;
}

.cta:hover {
  background-color: rgba(0, 136, 169, 0.8);
}
</style>
