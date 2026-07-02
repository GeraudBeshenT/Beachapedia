<script src="script.js?v=<?php echo time(); ?>"></script>
<script>
    function openModal() { document.getElementById("qgModal").style.display = "block"; }
    function closeModal() { document.getElementById("qgModal").style.display = "none"; }
    window.onclick = function(event) {
        let modal = document.getElementById("qgModal");
        if (event.target == modal) { closeModal(); }
    }
</script>

<div id="loginModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.75); z-index: 9999; justify-content: center; align-items: center;">
    
    <div class="modal-content" style="background: #2c3e50; border: 1px solid rgba(255,255,255,0.1); width: 100%; max-width: 400px; padding: 25px; border-radius: 8px; position: relative; font-family: 'Montserrat', sans-serif; color: white; box-shadow: 0 10px 30px rgba(0,0,0,0.5);">
        
        <span id="closeLoginModal" style="position: absolute; top: 12px; right: 15px; font-size: 1.4em; cursor: pointer; opacity: 0.6; transition: opacity 0.2s;">&times;</span>
        
        <div style="display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 10px;">
            <button type="button" id="tabLoginBtn" style="flex: 1; background: none; border: none; color: #ffcc00; font-weight: bold; cursor: pointer; font-size: 1.1em;">Se connecter</button>
            <button type="button" id="tabRegisterBtn" style="flex: 1; background: none; border: none; color: #ffffff; opacity: 0.5; font-weight: bold; cursor: pointer; font-size: 1.1em;">S'inscrire</button>
        </div>

        <form id="formLogin" method="POST" action="">
            <input type="hidden" name="action_auth" value="login">
            
            <div style="margin-bottom: 15px;">
                <label style="display: block; font-size: 0.9em; margin-bottom: 5px; opacity: 0.8;">Pseudo du joueur</label>
                <input type="text" name="pseudo" required style="width: 100%; padding: 10px; background: #1a252f; border: 1px solid rgba(255,255,255,0.2); border-radius: 4px; color: white; box-sizing: border-box;">
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; font-size: 0.9em; margin-bottom: 5px; opacity: 0.8;">Mot de passe</label>
                <input type="password" name="password" required style="width: 100%; padding: 10px; background: #1a252f; border: 1px solid rgba(255,255,255,0.2); border-radius: 4px; color: white; box-sizing: border-box;">
            </div>
            
            <button type="submit" style="width: 100%; padding: 11px; background: #2ecc71; border: none; border-radius: 4px; color: white; font-weight: bold; cursor: pointer; font-size: 1em;">Se connecter au jeu</button>
        </form>

        <form id="formRegister" method="POST" action="" style="display: none;">
            <input type="hidden" name="action_auth" value="register">
            
            <div style="margin-bottom: 15px;">
                <label style="display: block; font-size: 0.9em; margin-bottom: 5px; opacity: 0.8;">Pseudo choisi (Unique)</label>
                <input type="text" name="pseudo" required style="width: 100%; padding: 10px; background: #1a252f; border: 1px solid rgba(255,255,255,0.2); border-radius: 4px; color: white; box-sizing: border-box;">
            </div>
            
            <div style="margin-bottom: 15px;">
                <label style="display: block; font-size: 0.9em; margin-bottom: 5px; opacity: 0.8;">Identifiant In-Game (ID unique de jeu)</label>
                <input type="text" name="id_game" required style="width: 100%; padding: 10px; background: #1a252f; border: 1px solid rgba(255,255,255,0.2); border-radius: 4px; color: white; box-sizing: border-box;" placeholder="Ex: 1625">
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; font-size: 0.9em; margin-bottom: 5px; opacity: 0.8;">Mot de passe</label>
                <input type="password" name="password" required style="width: 100%; padding: 10px; background: #1a252f; border: 1px solid rgba(255,255,255,0.2); border-radius: 4px; color: white; box-sizing: border-box;">
            </div>
            
            <button type="submit" style="width: 100%; padding: 11px; background: #3498db; border: none; border-radius: 4px; color: white; font-weight: bold; cursor: pointer; font-size: 1em;">Créer mon compte joueur</button>
        </form>

    </div>
</div>
</body>
</html>