<template>
  <div>
    <Navbar />
    <div class="title">
      <h1>Déconnexion</h1>
    </div>
  </div>
</template>
<script>
export default {
  mounted() {
    localStorage.removeItem('token')
    localStorage.removeItem('userid')
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
